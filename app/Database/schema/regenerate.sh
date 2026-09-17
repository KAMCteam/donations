#!/usr/bin/env bash
# Regenerates donations_schema.sql from a freshly migrated and seeded database.
#
#   php spark migrate && php spark db:seed DatabaseSeeder
#   bash app/Database/schema/regenerate.sh [db] [user]
#
# The migrations remain the source of truth; this file only mirrors them for
# anyone importing the schema straight into phpMyAdmin.
set -euo pipefail

DB="${1:-donations}"
USER="${2:-root}"
OUT="$(dirname "$0")/donations_schema.sql"

echo "Regenerating $OUT from $DB ..." >&2
echo "Run the header by hand if it needs updating; this script refreshes the SQL below it." >&2

{
    sed -n '1,/^SET FOREIGN_KEY_CHECKS = 0;$/p' "$OUT"
    echo ""
    mariadb-dump -u "$USER" --no-data --skip-comments --skip-add-drop-table --skip-set-charset "$DB" | grep -v '^/\*!999999'
    echo ""
    echo "-- ---------------------------------------------------------------------------"
    echo "-- Reference data: the lab catalogue (LabCatalogueSeeder), and the migration"
    echo "-- log so the framework knows this schema is already at the latest version."
    echo "-- ---------------------------------------------------------------------------"
    echo ""
    mariadb-dump -u "$USER" --no-create-info --skip-comments --complete-insert --skip-set-charset "$DB" organ_programs lab_parents labs migrations | grep -v '^/\*!999999'
    echo ""
    echo "SET FOREIGN_KEY_CHECKS = 1;"
} > "$OUT.tmp"

mv "$OUT.tmp" "$OUT"
echo "Done." >&2
