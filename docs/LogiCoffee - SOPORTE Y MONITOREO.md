# LogiCoffee — Herramientas de soporte y monitoreo

Este documento describe las herramientas usadas para el soporte técnico, el diagnóstico y el monitoreo de LogiCoffee, y cómo se usan en el proyecto.

| Herramienta | Uso | Dónde está configurada |
|---|---|---|
| GitHub Issues | Seguimiento técnico y control de correcciones | `.github/ISSUE_TEMPLATE/`, `.github/pull_request_template.md` |
| Docker / Laravel Sail | Diagnóstico de infraestructura | `compose.yaml` |
| Grafana (+ Prometheus, cAdvisor, mysqld-exporter) | Monitoreo de infraestructura y rendimiento | `docker/monitoring/` |
| MySQL 8.4 | Diagnóstico y administración de datos | `docker/mysql/my.cnf`, `docker/mysql/diagnostico.sql` |

> En los comandos, `sail` equivale a `./vendor/bin/sail`. Para crear el alias: `alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'`

---

## 1. GitHub Issues

Repositorio: https://github.com/cristhian199228/logicofee/issues

### Registrar errores técnicos
Al pulsar **New issue** aparecen dos plantillas:

- **Error técnico** — fallos de la aplicación. Pide módulo afectado (Pedidos, Productos, Lotes, Reportes…), severidad, pasos para reproducir, resultado esperado, evidencias y solución.
- **Incidente de infraestructura** — contenedores caídos, MySQL, alertas de Grafana. Pide servicio afectado, cómo se detectó y la salida de diagnóstico.

Cada issue se crea con las etiquetas `bug` / `infraestructura` y `pendiente`.

### Asignar responsables
En el panel derecho del issue: **Assignees** → seleccionar al integrante. También se puede usar **Labels** para cambiar el estado (`pendiente` → `en progreso`) y **Milestone** para asociarlo al sprint.

### Adjuntar evidencias y describir soluciones
- Arrastrar capturas de pantalla al campo *Evidencias*.
- Pegar fragmentos de `storage/logs/laravel.log` o de `sail logs` en el campo *Salida de diagnóstico* (se muestran como bloque de código).
- Al cerrar, completar *Solución aplicada* con la causa raíz y el cambio realizado.

### Relacionar incidentes con modificaciones del código
- En el mensaje del commit: `git commit -m "Corrige cálculo de total en pedido (Fixes #12)"`
- En el Pull Request, la plantilla incluye `Closes #<número>`; al hacer merge, GitHub cierra el issue automáticamente y deja enlazado el commit en el historial del issue.

---

## 2. Docker / Laravel Sail

### Verificar si los contenedores están funcionando
```bash
sail up -d          # levantar todos los servicios
sail ps             # estado de cada contenedor (Up / Exited / healthy)
docker stats        # CPU y memoria en tiempo real
```
Servicios del proyecto: `laravel.test` (app), `mysql`, `redis`, `meilisearch`, `mailpit`, `selenium`, y los de monitoreo `grafana`, `prometheus`, `cadvisor`, `mysqld-exporter`.

`mysql` y `redis` tienen *healthcheck*: en `sail ps` deben aparecer como `(healthy)`.

### Revisar errores de ejecución
```bash
sail logs                      # logs de todos los servicios
sail logs -f laravel.test      # seguir logs de la aplicación
sail logs --tail=100 mysql     # últimas 100 líneas de MySQL
sail exec laravel.test tail -n 50 storage/logs/laravel.log   # errores de Laravel
sail artisan about             # versión, entorno, conexión a BD, caché, colas
```

### Identificar problemas en los servicios
| Síntoma | Comando | Causa frecuente |
|---|---|---|
| La web no carga | `sail ps`, `sail logs laravel.test` | Puerto 80 ocupado (`APP_PORT` en `.env`) |
| `SQLSTATE[HY000] [2002] Connection refused` | `sail ps mysql`, `sail logs mysql` | MySQL aún iniciando o caído |
| Contenedor en `Exited` | `docker inspect <contenedor> --format '{{.State.ExitCode}}'` | Error de configuración, memoria insuficiente (137) |
| Lentitud general | `docker stats`, Grafana | Contenedor con CPU/memoria alta |

Reiniciar un servicio: `sail restart mysql`. Reconstruir la imagen: `sail build --no-cache`.

---

## 3. Grafana

Grafana se levanta junto con Sail y viene **preconfigurado** (no hay que crear nada a mano):

- **URL:** http://localhost:3000 — usuario `admin`, contraseña `admin` (configurable con `GRAFANA_USER` / `GRAFANA_PASSWORD` en `.env`)
- **Prometheus:** http://localhost:9090 (recolecta las métricas cada 15 s)

Arquitectura:
```
cAdvisor ──(CPU/memoria de contenedores)──┐
                                          ├──► Prometheus ──► Grafana (paneles + alertas)
mysqld-exporter ──(métricas MySQL)────────┘                     │
MySQL (consultas directas a performance_schema) ────────────────┘
```

### Visualizar consumo de CPU y memoria
Menú **Dashboards → LogiCoffee → LogiCoffee - Infraestructura y Base de Datos**. Sección *Contenedores Docker / Sail*:
- Contenedores activos, CPU total, memoria total, estado de MySQL.
- Uso de CPU y de memoria por contenedor.
- Tráfico de red y tiempo desde el último arranque (detecta reinicios).

### Analizar tendencias de rendimiento
Cambiar el rango de tiempo arriba a la derecha (última hora, 6 h, 24 h, 7 días). Las leyendas muestran promedio, máximo y último valor. Sección *MySQL 8.4*: conexiones, consultas por segundo y consultas lentas por minuto.

### Crear paneles y alertas de monitoreo
Alertas incluidas (menú **Alerting → Alert rules**, carpeta LogiCoffee):

| Alerta | Condición | Severidad |
|---|---|---|
| CPU alta en contenedor | > 80% durante 2 min | advertencia |
| Memoria alta en contenedor | > 1 GB durante 2 min | advertencia |
| MySQL no responde | `mysql_up` = 0 durante 1 min | crítica |
| Conexiones MySQL cerca del límite | > 80% de `max_connections` | advertencia |
| Consultas lentas en MySQL | al menos una consulta > 1 s en 5 min | informativa |

Crear un panel nuevo: **Dashboards → New → Add visualization → Prometheus**, escribir una consulta (ej. `container_memory_working_set_bytes{container_label_com_docker_compose_service="mysql"}`) y guardar.
Crear una alerta nueva: **Alerting → Alert rules → New alert rule**, elegir la consulta, el umbral y la carpeta LogiCoffee. Para recibir notificaciones (correo, Telegram, Discord) se configura en **Alerting → Contact points**.

Cuando se dispara una alerta, se registra un issue con la plantilla *Incidente de infraestructura* adjuntando la captura del panel.

---

## 4. MySQL 8.4

Configuración adicional en `docker/mysql/my.cnf` (se carga automáticamente):
- `slow_query_log = ON` y `long_query_time = 1` → registra toda consulta que tarde más de 1 segundo, en `/var/lib/mysql/slow.log` y en la tabla `mysql.slow_log`.
- `performance_schema = ON` → estadísticas por consulta.

Conectarse:
```bash
sail mysql                                            # como usuario de la app (sail)
docker compose exec mysql mysql -uroot -ppassword     # como root (necesario para ver todos los procesos)
```

Ejecutar el diagnóstico completo de una vez:
```bash
docker compose exec -T mysql mysql -uroot -ppassword < docker/mysql/diagnostico.sql
```

### Revisar conexiones y procesos activos
```sql
SHOW PROCESSLIST;
SHOW GLOBAL STATUS LIKE 'Threads_%';
SHOW VARIABLES LIKE 'max_connections';
KILL <id>;   -- terminar un proceso bloqueado
```
También visible en Grafana, panel *Procesos activos*.

### Identificar consultas lentas
```sql
SHOW GLOBAL STATUS LIKE 'Slow_queries';
SELECT start_time, query_time, rows_examined, sql_text FROM mysql.slow_log ORDER BY query_time DESC LIMIT 10;
SELECT * FROM sys.statement_analysis WHERE db = 'laravel' LIMIT 10;
EXPLAIN SELECT ...;   -- analizar el plan de una consulta concreta
```
Desde el host: `sail exec mysql tail -f /var/lib/mysql/slow.log`. En Grafana: paneles *Top 10 consultas más costosas* y *Registro de consultas lentas*.

### Verificar el estado de la base de datos
```bash
sail exec mysql mysqladmin -uroot -ppassword status
sail artisan db:show            # tamaño, conexiones y tablas
sail artisan db:table pedidos   # columnas, índices y claves foráneas
sail artisan migrate:status     # migraciones aplicadas
```
```sql
SELECT * FROM sys.schema_tables_with_full_table_scans WHERE object_schema = 'laravel';  -- índices faltantes
SELECT * FROM sys.innodb_lock_waits;  -- bloqueos
```

---

## Flujo de soporte

1. **Detección:** alerta de Grafana, contenedor caído en `sail ps` o reporte de usuario.
2. **Registro:** se crea un issue con la plantilla correspondiente y se asigna un responsable.
3. **Diagnóstico:** `sail logs`, `laravel.log`, paneles de Grafana y consultas de `diagnostico.sql`; la salida se adjunta al issue.
4. **Corrección:** rama + commit/PR con `Fixes #N`.
5. **Cierre:** merge del PR (cierra el issue) y verificación en Grafana de que las métricas volvieron a la normalidad.
