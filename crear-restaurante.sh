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

# ── Paso 4: Crear proxy host con SSL (como lo hace la UI de NPM) ─
echo ""
echo "→ [4/4] Creando proxy host con certificado SSL..."

# Crear proxy y solicitar Let's Encrypt en una sola llamada
# Esto replica exactamente lo que hace la UI de NPM cuando seleccionas
# "Request a new Certificate" con Force SSL activado
NPM_HOST=$(curl -s --max-time 120 -X POST "$NPM_URL/api/nginx/proxy-hosts" \
    -H "Authorization: Bearer $NPM_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{
        \"domain_names\": [\"$DOMINIO\"],
        \"forward_scheme\": \"http\",
        \"forward_host\": \"$FRONTEND_HOST\",
        \"forward_port\": $FRONTEND_PORT,
        \"ssl_forced\": true,
        \"http2_support\": true,
        \"block_exploits\": true,
        \"allow_websocket_upgrade\": true,
        \"enabled\": true,
        \"certificate_id\": \"new\",
        \"meta\": {
            \"letsencrypt_agree\": true,
            \"letsencrypt_email\": \"$NPM_EMAIL\",
            \"dns_challenge\": false
        }
    }")

NPM_ID=$(echo "$NPM_HOST" | python3 -c "
import sys, json
d = json.load(sys.stdin)
cid = d.get('id', '')
print(cid if cid else '')
" 2>/dev/null || echo "")

CERT_ID=$(echo "$NPM_HOST" | python3 -c "
import sys, json
d = json.load(sys.stdin)
cid = d.get('certificate_id', 0)
print(cid if cid and cid != '0' else 0)
" 2>/dev/null || echo "0")

if [ -n "$NPM_ID" ] && [ "$NPM_ID" != "None" ]; then
    echo "  ✓ Proxy host creado (NPM ID: $NPM_ID)"

    if [ "$CERT_ID" != "0" ] && [ -n "$CERT_ID" ]; then
        echo "  ✓ SSL activado automáticamente (Cert ID: $CERT_ID)"
        SSL_OK=true
    else
        # El proxy se creó pero sin SSL (puede pasar si LE falló internamente)
        # Intentar solicitar el certificado por separado
        echo "  ~ Proxy creado sin SSL — intentando solicitar certificado aparte..."
        sleep 5

        CERT_RESPONSE=$(curl -s --max-time 120 -X POST "$NPM_URL/api/nginx/certificates" \
            -H "Authorization: Bearer $NPM_TOKEN" \
            -H "Content-Type: application/json" \
            -d "{
                \"provider\": \"letsencrypt\",
                \"domain_names\": [\"$DOMINIO\"],
                \"meta\": {
                    \"letsencrypt_agree\": true,
                    \"letsencrypt_email\": \"$NPM_EMAIL\",
                    \"dns_challenge\": false
                }
            }")

        NEW_CERT_ID=$(echo "$CERT_RESPONSE" | python3 -c "
import sys, json
d = json.load(sys.stdin)
cid = d.get('id', 0)
if isinstance(cid, int) and cid > 0:
    print(cid)
elif isinstance(cid, str) and cid.isdigit() and int(cid) > 0:
    print(cid)
else:
    print(0)
" 2>/dev/null || echo "0")

        if [ "$NEW_CERT_ID" != "0" ] && [ -n "$NEW_CERT_ID" ]; then
            echo "  ✓ Certificado SSL generado (ID: $NEW_CERT_ID)"

            # Actualizar proxy con el certificado
            curl -s -X PUT "$NPM_URL/api/nginx/proxy-hosts/$NPM_ID" \
                -H "Authorization: Bearer $NPM_TOKEN" \
                -H "Content-Type: application/json" \
                -d "{
                    \"domain_names\": [\"$DOMINIO\"],
                    \"forward_scheme\": \"http\",
                    \"forward_host\": \"$FRONTEND_HOST\",
                    \"forward_port\": $FRONTEND_PORT,
                    \"ssl_forced\": true,
                    \"http2_support\": true,
                    \"block_exploits\": true,
                    \"allow_websocket_upgrade\": true,
                    \"enabled\": true,
                    \"certificate_id\": $NEW_CERT_ID
                }" > /dev/null

            echo "  ✓ SSL activado y forzado"
            SSL_OK=true
        else
            # Mostrar el error exacto de NPM para diagnóstico
            CERT_ERR=$(echo "$CERT_RESPONSE" | python3 -c "
import sys, json
d = json.load(sys.stdin)
err = d.get('error', {})
if isinstance(err, dict):
    print(err.get('message', json.dumps(d)))
else:
    print(json.dumps(d))
" 2>/dev/null || echo "$CERT_RESPONSE")
            echo "  ✗ No se pudo generar SSL automáticamente"
            echo "    Respuesta NPM: $CERT_ERR"
            echo "  → Actívalo manualmente: NPM → edita el proxy → SSL → Let's Encrypt → Force SSL → Save"
            SSL_OK=false
        fi
    fi
else
    ERR=$(echo "$NPM_HOST" | python3 -c "
import sys, json
d = json.load(sys.stdin)
err = d.get('error', {})
if isinstance(err, dict):
    print(err.get('message', json.dumps(d)))
else:
    print(json.dumps(d))
" 2>/dev/null || echo "$NPM_HOST")
    echo "  ✗ Error creando proxy: $ERR"
    SSL_OK=false
fi

# ── Resumen ─────────────────────────────────────────════════════
echo ""
echo "════════════════════════════════════════════════════════"
if [ "$SSL_OK" = "true" ]; then
    echo "  ✓  ¡Restaurante creado exitosamente con SSL!"
else
    echo "  ✓  ¡Restaurante creado! (SSL pendiente — ver instrucciones arriba)"
fi
echo ""
echo "  URL:           https://$DOMINIO"
echo "  Usuario:       $EMAIL"
echo "  Contraseña:    $PASSWORD"
echo "  Tenant ID:     $TENANT_ID"
echo "  Base de datos: restaurante_$TENANT_ID"
echo "════════════════════════════════════════════════════════"
echo ""
