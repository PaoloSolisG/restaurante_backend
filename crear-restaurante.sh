#!/bin/bash
# ═══════════════════════════════════════════════════════════════
#  crear-restaurante.sh
#  Uso: bash crear-restaurante.sh "Nombre" "dominio.naniva.cloud" "email@x.com" "password"
# ═══════════════════════════════════════════════════════════════
set -e

# ── Configuración ───────────────────────────────────────────────
NPM_URL="http://127.0.0.1:81"
NPM_EMAIL="paolosolisgomez1@gmail.com"
NPM_PASSWORD="Paolosu123."
API_URL="https://restoapi.naniva.cloud/api"
FRONTEND_HOST="restaurante-frontend"
FRONTEND_PORT=80
APP_CONTAINER="restaurante-app"

# ── Argumentos ──────────────────────────────────────────────────
NOMBRE="${1:-}"
DOMINIO="${2:-}"
EMAIL="${3:-}"
PASSWORD="${4:-admin123}"

if [ -z "$NOMBRE" ] || [ -z "$DOMINIO" ] || [ -z "$EMAIL" ]; then
    echo ""
    echo "Uso: bash crear-restaurante.sh \"Nombre\" \"dominio.naniva.cloud\" \"email@ejemplo.com\" \"password\""
    echo "Ejemplo: bash crear-restaurante.sh \"Querico\" \"querico.naniva.cloud\" \"admin@querico.com\" \"admin123\""
    echo ""
    exit 1
fi

json_get() {
    python3 -c "import sys,json; d=json.load(sys.stdin); print($1)" 2>/dev/null || echo ""
}

echo ""
echo "════════════════════════════════════════════════════════"
echo "  Nuevo Restaurante: $NOMBRE"
echo "  Dominio:           $DOMINIO"
echo "  Email admin:       $EMAIL"
echo "════════════════════════════════════════════════════════"

# ── Paso 1: Crear tenant + migraciones ─────────────────────────
echo ""
echo "→ [1/4] Creando tenant y ejecutando migraciones..."

RESPONSE=$(curl -s -X POST "$API_URL/tenants" \
    -H "Content-Type: application/json" \
    -d "{\"nombre\":\"$NOMBRE\",\"dominio\":\"$DOMINIO\",\"email\":\"$EMAIL\",\"plan\":\"basic\"}")

STATUS=$(echo "$RESPONSE" | python3 -c "import sys,json; d=json.load(sys.stdin); print(str(d.get('status',False)).lower())" 2>/dev/null || echo "false")

if [ "$STATUS" != "true" ]; then
    MSG=$(echo "$RESPONSE" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('message','Error desconocido'))" 2>/dev/null || echo "$RESPONSE")
    echo "  ✗ Error: $MSG"
    exit 1
fi

TENANT_ID=$(echo "$RESPONSE" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['id'])")
echo "  ✓ Tenant ID:       $TENANT_ID"
echo "  ✓ Base de datos:   restaurante_$TENANT_ID"
echo "  ✓ Migraciones ejecutadas"

# ── Paso 2: Crear usuario admin ─────────────────────────────────
echo ""
echo "→ [2/4] Creando usuario administrador..."

docker exec "$APP_CONTAINER" php artisan tinker --execute="
tenancy()->initialize('$TENANT_ID');
\$u = \App\Models\Usuario::create([
    'nombre'   => 'Admin',
    'email'    => '$EMAIL',
    'password' => bcrypt('$PASSWORD'),
    'role_id'  => 1,
]);
echo \$u->id ? 'ok' : 'error';
" 2>/dev/null | grep -q "ok" && echo "  ✓ Usuario creado: $EMAIL" || echo "  ✗ Error creando usuario"

# ── Paso 3: Login NPM ───────────────────────────────────────────
echo ""
echo "→ [3/4] Conectando con Nginx Proxy Manager..."

NPM_RESPONSE=$(curl -s -X POST "$NPM_URL/api/tokens" \
    -H "Content-Type: application/json" \
    -d "{\"identity\":\"$NPM_EMAIL\",\"secret\":\"$NPM_PASSWORD\"}")

NPM_TOKEN=$(echo "$NPM_RESPONSE" | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])" 2>/dev/null || echo "")

if [ -z "$NPM_TOKEN" ]; then
    echo "  ✗ No se pudo conectar con NPM. Verifica credenciales."
    echo "  (El restaurante fue creado igualmente — agrega el proxy en NPM manualmente)"
    exit 0
fi
echo "  ✓ Conectado a NPM"

# Buscar certificado wildcard *.naniva.cloud
CERT_ID=$(curl -s "$NPM_URL/api/nginx/certificates?expand=owner" \
    -H "Authorization: Bearer $NPM_TOKEN" | \
    python3 -c "
import sys,json
certs = json.load(sys.stdin)
for c in certs:
    for d in c.get('domain_names', []):
        if 'naniva.cloud' in d:
            print(c['id'])
            sys.exit()
print(0)
" 2>/dev/null || echo "0")

[ "$CERT_ID" != "0" ] && echo "  ✓ Certificado SSL wildcard encontrado (ID: $CERT_ID)" || echo "  ~ Sin certificado wildcard, se creará sin SSL forzado"

# ── Paso 4: Crear proxy host ────────────────────────────────────
echo ""
echo "→ [4/4] Creando proxy host en NPM..."

SSL_FORCED=true
[ "$CERT_ID" = "0" ] && SSL_FORCED=false

NPM_HOST=$(curl -s -X POST "$NPM_URL/api/nginx/proxy-hosts" \
    -H "Authorization: Bearer $NPM_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{
        \"domain_names\": [\"$DOMINIO\"],
        \"forward_scheme\": \"http\",
        \"forward_host\": \"$FRONTEND_HOST\",
        \"forward_port\": $FRONTEND_PORT,
        \"ssl_forced\": $SSL_FORCED,
        \"http2_support\": true,
        \"block_exploits\": true,
        \"allow_websocket_upgrade\": true,
        \"enabled\": true,
        \"certificate_id\": $CERT_ID
    }")

NPM_ID=$(echo "$NPM_HOST" | python3 -c "import sys,json; print(json.load(sys.stdin).get('id','?'))" 2>/dev/null || echo "?")

if [ "$NPM_ID" != "?" ] && [ -n "$NPM_ID" ]; then
    echo "  ✓ Proxy host creado (NPM ID: $NPM_ID)"
else
    ERROR=$(echo "$NPM_HOST" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('error',{}).get('message','?'))" 2>/dev/null || echo "?")
    echo "  ✗ Error en NPM: $ERROR"
fi

# ── Resumen ─────────────────────────────────────────────────────
echo ""
echo "════════════════════════════════════════════════════════"
echo "  ✓  ¡Restaurante creado exitosamente!"
echo ""
echo "  URL:           https://$DOMINIO"
echo "  Usuario:       $EMAIL"
echo "  Contraseña:    $PASSWORD"
echo "  Tenant ID:     $TENANT_ID"
echo "  Base de datos: restaurante_$TENANT_ID"
echo "════════════════════════════════════════════════════════"
echo ""
