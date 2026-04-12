# Postman collection

Import `jobs-monitor.postman_collection.json` into Postman to hit all
API endpoints of the package.

## Setup

1. Make sure the host app has `JOBS_MONITOR_API_ENABLED=true` in its
   `.env` file. The API is disabled by default.
2. Check the `base_url` collection variable:
   - `http://localhost:8090` — Laravel 13 test app
   - `http://localhost:8080` — Laravel 10 test app

## What's in the collection

Four endpoint groups, matching `routes/api.php`:

### Jobs — `GET /api/jobs-monitor/jobs`

Paginated list of job records with full filter and sort support.

| Query param        | Values                                                   |
|--------------------|----------------------------------------------------------|
| `period`           | `1m` `5m` `30m` `1h` `6h` `24h` `7d` `30d` `all`         |
| `search`           | Substring, case-insensitive, matched on `job_class`      |
| `status`           | `processing` `processed` `failed`                        |
| `queue`            | Exact match, e.g. `default`, `emails`                    |
| `connection`       | Exact match, e.g. `redis`, `database`, `sqs`             |
| `failure_category` | `transient` `permanent` `critical` `unknown`             |
| `sort`             | `started_at` `status` `duration_ms` `job_class`          |
| `dir`              | `asc` `desc`                                             |
| `page`             | 1-based page number                                      |
| `per_page`         | Max 200                                                  |

### Attempts — `GET /api/jobs-monitor/jobs/{uuid}/attempts`

Returns every stored attempt for a given job UUID ordered by attempt
number ascending. Useful for rendering a retry timeline. Set the
`job_uuid` collection variable to the UUID you want to inspect.

### Failures — `GET /api/jobs-monitor/failures`

Shortcut that always forces `status=failed`. Accepts the same params
as `jobs` except `status` is ignored.

### Stats — `GET /api/jobs-monitor/stats`

Aggregate stats for a **single** job class:

| Query param | Notes                                    |
|-------------|------------------------------------------|
| `period`    | Same values as above                     |
| `job_class` | **Required**, fully qualified class name |

### Stats overview — `GET /api/jobs-monitor/stats/overview`

Aggregated per-class stats across all job classes in the period:
`total`, `processed`, `failed`, `avg_duration_ms`, `max_duration_ms`,
`retry_count`. Powers the Stats page.

| Query param | Notes                                    |
|-------------|------------------------------------------|
| `period`    | Same values as above                     |

## Response shape

All list endpoints return:

```json
{
  "data": [ /* array of records */ ],
  "meta": { "total": 0, "page": 1, "per_page": 50, "last_page": 1 }
}
```

Each record includes: `uuid`, `attempt`, `job_class`, `connection`,
`queue`, `status`, `started_at`, `finished_at`, `duration_ms`,
`exception`, **`failure_category`** and the redacted `payload`.
