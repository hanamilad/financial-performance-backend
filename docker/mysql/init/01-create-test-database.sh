#!/bin/sh
#
# Single source of truth for the isolated test database (FOUNDATION-003, DEC-037/038).
#
# Creates ONLY `financial_performance_test` and grants the application user
# (MYSQL_USER) privileges scoped to that database. It never touches the
# development database `financial_performance`, never grants global privileges,
# and never contains or prints a password — credentials are read from the
# container environment that compose.yaml already provides.
#
# Two execution paths, one file:
#   1. Fresh clone  — MySQL runs this automatically from
#                     /docker-entrypoint-initdb.d on first volume init.
#   2. Existing volume — run the SAME file manually (docker-entrypoint-initdb.d
#                     does not run retroactively):
#                       docker compose exec mysql sh \
#                         /docker-entrypoint-initdb.d/01-create-test-database.sh
#
# Idempotent: safe to run repeatedly (CREATE DATABASE IF NOT EXISTS + GRANT).

set -eu

: "${MYSQL_ROOT_PASSWORD:?MYSQL_ROOT_PASSWORD is required}"
: "${MYSQL_USER:?MYSQL_USER is required}"

mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS \`financial_performance_test\`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON \`financial_performance_test\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL
