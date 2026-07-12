#!/usr/bin/env bash
set -euo pipefail

ENV_FILE="${1:-/etc/simpa-backup.env}"

if [ ! -r "${ENV_FILE}" ]; then
    echo "Backup environment file is not readable: ${ENV_FILE}" >&2
    exit 1
fi

set -a
# shellcheck disable=SC1090
. "${ENV_FILE}"
set +a

: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"

BACKUP_DIR="${BACKUP_DIR:-/var/backups/simpa/mysql}"
BACKUP_RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BASE_PATH="${BACKUP_DIR}/${DB_DATABASE}-${TIMESTAMP}.sql.gz"

install -d -m 0700 "${BACKUP_DIR}"

DEFAULTS_FILE="$(mktemp)"
cleanup() {
    rm -f "${DEFAULTS_FILE}"
}
trap cleanup EXIT

{
    echo "[client]"
    echo "user=${DB_USERNAME}"
    echo "password=${DB_PASSWORD}"
    if [ -n "${DB_SOCKET:-}" ] && [ -S "${DB_SOCKET}" ]; then
        echo "socket=${DB_SOCKET}"
    else
        echo "host=${DB_HOST:-127.0.0.1}"
        echo "port=${DB_PORT:-3306}"
    fi
} > "${DEFAULTS_FILE}"
chmod 600 "${DEFAULTS_FILE}"

if [ -n "${BACKUP_ENCRYPTION_PASSPHRASE:-}" ]; then
    OUTPUT_PATH="${BASE_PATH}.enc"
    BACKUP_ENCRYPTION_PASSPHRASE="${BACKUP_ENCRYPTION_PASSPHRASE}" \
        mysqldump --defaults-extra-file="${DEFAULTS_FILE}" \
            --single-transaction \
            --quick \
            --routines \
            --triggers \
            --events \
            "${DB_DATABASE}" \
        | gzip -c \
        | openssl enc -aes-256-cbc -salt -pbkdf2 -pass env:BACKUP_ENCRYPTION_PASSPHRASE -out "${OUTPUT_PATH}"
else
    OUTPUT_PATH="${BASE_PATH}"
    mysqldump --defaults-extra-file="${DEFAULTS_FILE}" \
        --single-transaction \
        --quick \
        --routines \
        --triggers \
        --events \
        "${DB_DATABASE}" \
        | gzip -c > "${OUTPUT_PATH}"
fi

chmod 600 "${OUTPUT_PATH}"

find "${BACKUP_DIR}" -type f \
    \( -name "${DB_DATABASE}-*.sql.gz" -o -name "${DB_DATABASE}-*.sql.gz.enc" \) \
    -mtime "+${BACKUP_RETENTION_DAYS}" \
    -delete

echo "Created database backup: ${OUTPUT_PATH}"
