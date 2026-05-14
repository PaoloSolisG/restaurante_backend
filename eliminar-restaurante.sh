#!/bin/bash
# ═══════════════════════════════════════════════════════════════
#  eliminar-restaurante.sh
#  Uso: bash eliminar-restaurante.sh "tenant-id"
#  Ejemplo: bash eliminar-restaurante.sh "don-ceviche"
#
#  Elimina completamente:
#    ✓ Base de datos del tenant
#    ✓ Registro del tenant en la BD central
#    ✓ Proxy host en NPM
#    ✓ Certificado SSL en NPM
# ═══════════════════════════════════════════════════════════════

NPM_URL="http://127.0.0.1:81"
NPM_EMAIL="paolosolisgomez1@gmail.com"
NPM_PASSWORD="Paolosu123."
APP_CONTAINER="restaurante-app"

TENANT_ID="${1:-}"

if [ -z "$TENANT_ID" ]; then
    echo ""
    echo "Uso: bash eliminar-restaurante.sh \"tenant-id\""
    echo "Ejemplo: bash eliminar-restaurante.sh \"don-ceviche\""
    echo ""
    echo "Para ver todos los tenants existentes:"
    echo "  docker exec $APP_CONTAINER php artisan tinker --execute=\"App\\\\Models\\\\Tenant::all(['id','data'])->each(fn(\\\$t)=>print(\\\$t->id.' | '.(\\\$t->data['dominio']??'-').\\\"\n\\\"));\""
    echo ""
    exit 1
fi

# ── Obtener info del tenant ──────────────────────────────────────
echo ""
echo "════════════════════════════════════════════════════════"
echo "  Eliminando tenant: $TENANT_ID"
echo "════════════════════════════════════════════════════════"

echo ""
echo "→ [1/4] Buscando información del tenant..."

TENANT_INFO=$(docker exec "$APP_CONTAINER" php artisan tinker --execute="
\$t = App\Models\Tenant::find('$TENANT_ID');
if (\$t) {
    echo json_encode(['found'=>true,'dominio'=>\$t->data['dominio']??'','nombre'=>\$t->data['nombre']??'']);
} else {
    echo json_encode(['found'=>false]);
}
" 2>/dev/null | grep -o '{.*}' | tail -1)

FOUND=$(echo "$TENANT_INFO" | python3 -c "import sys,json; print(json.load(sys.stdin).get('found','false'))" 2>/dev/null || echo "false")

if [ "$FOUND" != "True" ] && [ "$FOUND" != "true" ]; then
    echo "  ✗ Tenant '$TENANT_ID' no encontrado en la base de datos"
    echo "  → Verifica el ID con:"
    echo "    docker exec $APP_CONTAINER php artisan tinker --execute=\"App\\\\Models\\\\Tenant::all(['id'])->pluck('id');\""
    exit 1
fi

DOMINIO=$(echo "$TENANT_INFO" | python3 -c "import sys,json; print(json.load(sys.stdin).get('dominio',''))" 2>/dev/null || echo "")
NOMBRE=$(echo "$TENANT_INFO" | python3 -c "import sys,json; print(json.load(sys.stdin).get('nombre',''))" 2>/dev/null || echo "")

echo "  ✓ Tenant encontrado"
echo "    Nombre:  $NOMBRE"
echo "    Dominio: $DOMINIO"
echo "    BD:      restaurante_$TENANT_ID"

# ── Confirmación ─────────────────────────────────────────────────
echo ""
echo "  ⚠  Se eliminará TODO permanentemente. Esta acción NO tiene vuelta atrás."
printf "  ¿Continuar? [s/N]: "
read -r CONFIRM
if [ "$CONFIRM" != "s" ] && [ "$CONFIRM" != "S" ]; then
    echo "  Cancelado."
    exit 0
fi

# ── Paso 2: Eliminar proxy + cert en NPM ─────────────────────────
echo ""
echo "→ [2/4] Conectando con Nginx Proxy Manager..."

NPM_TOKEN=$(curl -s -X POST "$NPM_URL/api/tokens" \
    -H "Content-Type: application/json" \
    -d "{\"identity\":\"$NPM_EMAIL\",\"secret\":\"$NPM_PASSWORD\"}" | \
    python3 -c "import sys,json; print(json.load(sys.stdin).get('token',''))" 2>/dev/null || echo "")

if [ -z "$NPM_TOKEN" ]; then
    echo "  ✗ No se pudo conectar con NPM — omitiendo limpieza de proxy"
    NPM_OK=false
else
    echo "  ✓ Conectado a NPM"

    # Buscar el proxy host por dominio
    ALL_HOSTS=$(curl -s "$NPM_URL/api/nginx/proxy-hosts" \
        -H "Authorization: Bearer $NPM_TOKEN")

    PROXY_DATA=$(echo "$ALL_HOSTS" | python3 -c "
import sys, json
hosts = json.load(sys.stdin)
dominio = '$DOMINIO'
for h in hosts:
    if dominio in h.get('domain_names', []):
        print(json.dumps({'id': h['id'], 'cert_id': h.get('certificate_id', 0)}))
        break
" 2>/dev/null || echo "")

    PROXY_ID=$(echo "$PROXY_DATA" | python3 -c "import sys,json; print(json.load(sys.stdin).get('id',''))" 2>/dev/null || echo "")
    CERT_ID=$(echo "$PROXY_DATA"  | python3 -c "import sys,json; print(json.load(sys.stdin).get('cert_id',0))" 2>/dev/null || echo "0")

    if [ -n "$PROXY_ID" ] && [ "$PROXY_ID" != "None" ]; then
        # Eliminar proxy host
        DEL_PROXY=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE \
            "$NPM_URL/api/nginx/proxy-hosts/$PROXY_ID" \
            -H "Authorization: Bearer $NPM_TOKEN")

        if [ "$DEL_PROXY" = "200" ]; then
            echo "  ✓ Proxy host eliminado (ID: $PROXY_ID)"
        else
            echo "  ~ No se pudo eliminar proxy (HTTP $DEL_PROXY) — elimínalo manualmente en NPM"
        fi

        # Eliminar certificado SSL si tiene uno
        if [ "$CERT_ID" != "0" ] && [ -n "$CERT_ID" ] && [ "$CERT_ID" != "None" ]; then
            DEL_CERT=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE \
                "$NPM_URL/api/nginx/certificates/$CERT_ID" \
                -H "Authorization: Bearer $NPM_TOKEN")

            if [ "$DEL_CERT" = "200" ]; then
                echo "  ✓ Certificado SSL eliminado (ID: $CERT_ID)"
            else
                echo "  ~ No se pudo eliminar certificado (HTTP $DEL_CERT) — elimínalo manualmente en NPM"
            fi
        else
            echo "  ~ Sin certificado SSL asociado"
        fi
        NPM_OK=true
    else
        echo "  ~ No se encontró proxy para '$DOMINIO' en NPM (quizás ya fue eliminado)"
        NPM_OK=true
    fi
fi

# ── Paso 3: Eliminar base de datos del tenant ────────────────────
echo ""
echo "→ [3/4] Eliminando base de datos restaurante_$TENANT_ID..."

DB_RESULT=$(docker exec "$APP_CONTAINER" php artisan tinker --execute="
try {
    \DB::statement('DROP DATABASE IF EXISTS \`restaurante_$TENANT_ID\`');
    echo 'ok';
} catch (Exception \$e) {
    echo 'error: '.\$e->getMessage();
}
" 2>/dev/null | grep -E "^(ok|error)" | tail -1)

if [ "$DB_RESULT" = "ok" ]; then
    echo "  ✓ Base de datos eliminada"
else
    echo "  ✗ $DB_RESULT"
fi

# ── Paso 4: Eliminar registro del tenant ─────────────────────────
echo ""
echo "→ [4/4] Eliminando registro del tenant..."

TENANT_RESULT=$(docker exec "$APP_CONTAINER" php artisan tinker --execute="
\$t = App\Models\Tenant::find('$TENANT_ID');
if (\$t) {
    \$t->delete();
    echo 'ok';
} else {
    echo 'not_found';
}
" 2>/dev/null | grep -E "^(ok|not_found)" | tail -1)

if [ "$TENANT_RESULT" = "ok" ]; then
    echo "  ✓ Tenant eliminado de la BD central"
elif [ "$TENANT_RESULT" = "not_found" ]; then
    echo "  ~ Tenant ya no existía en la BD central"
else
    echo "  ✗ Error eliminando tenant: $TENANT_RESULT"
fi

# ── Resumen ──────────────────────────────────────────────────────
echo ""
echo "════════════════════════════════════════════════════════"
echo "  ✓ Limpieza completada para: $TENANT_ID"
echo ""
echo "  Eliminado:"
echo "    • Base de datos: restaurante_$TENANT_ID"
echo "    • Registro tenant en BD central"
if [ "$NPM_OK" = "true" ]; then
    echo "    • Proxy host NPM ($DOMINIO)"
    echo "    • Certificado SSL"
fi
echo "════════════════════════════════════════════════════════"
echo ""
