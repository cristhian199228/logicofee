-- =====================================================================
-- LogiCoffee - Consultas de diagnóstico MySQL 8.4
-- Ejecutar:  docker compose exec -T mysql mysql -uroot -ppassword < docker/mysql/diagnostico.sql
--   o bien:  docker compose exec mysql mysql -uroot -p   (contraseña = DB_PASSWORD del .env)
-- =====================================================================

-- 1. Estado general del servidor
SELECT VERSION() AS version_mysql, @@hostname AS host, NOW() AS fecha;
SHOW GLOBAL STATUS WHERE Variable_name IN (
  'Uptime', 'Threads_connected', 'Threads_running', 'Max_used_connections',
  'Questions', 'Slow_queries', 'Aborted_connects', 'Aborted_clients'
);
SHOW VARIABLES WHERE Variable_name IN (
  'max_connections', 'slow_query_log', 'long_query_time', 'slow_query_log_file'
);

-- 2. Conexiones y procesos activos
SELECT id, user, host, db, command, time AS segundos, state, LEFT(info, 120) AS consulta
FROM information_schema.PROCESSLIST
WHERE command <> 'Daemon'
ORDER BY time DESC;

-- Conexiones agrupadas por usuario
SELECT user, COUNT(*) AS conexiones FROM information_schema.PROCESSLIST GROUP BY user;

-- 3. Consultas lentas registradas (slow log en tabla)
SELECT start_time, query_time, rows_examined, rows_sent, db, LEFT(sql_text, 150) AS consulta
FROM mysql.slow_log
ORDER BY query_time DESC
LIMIT 10;

-- Top 10 consultas que más tiempo consumen (performance_schema)
SELECT LEFT(DIGEST_TEXT, 120)                 AS consulta,
       COUNT_STAR                             AS ejecuciones,
       ROUND(AVG_TIMER_WAIT / 1e12, 4)        AS promedio_seg,
       ROUND(SUM_TIMER_WAIT / 1e12, 4)        AS total_seg,
       SUM_ROWS_EXAMINED                      AS filas_examinadas,
       SUM_NO_INDEX_USED                      AS sin_indice
FROM performance_schema.events_statements_summary_by_digest
WHERE SCHEMA_NAME = 'laravel'
ORDER BY SUM_TIMER_WAIT DESC
LIMIT 10;

-- 4. Estado de la base de datos de la aplicación
SELECT table_name                                   AS tabla,
       table_rows                                   AS filas_aprox,
       ROUND((data_length + index_length) / 1024, 1) AS tamano_kb
FROM information_schema.TABLES
WHERE table_schema = 'laravel'
ORDER BY (data_length + index_length) DESC;

-- Tablas con escaneos completos (posibles índices faltantes)
SELECT * FROM sys.schema_tables_with_full_table_scans WHERE object_schema = 'laravel';

-- Bloqueos en curso
SELECT * FROM sys.innodb_lock_waits;
