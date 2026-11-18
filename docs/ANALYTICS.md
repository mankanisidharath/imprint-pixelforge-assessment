# Product analytics with ClickHouse

The app's transactional database is **ClickHouse Managed Postgres**. Everything that must be exact and
atomic stays there: row locks, transactions, exactly-once settlement and webhook de-duplication.

The `generations` table is replicated continuously into **ClickHouse** (database `analytics`) with
ClickPipes Postgres CDC (initial load + CDC, NULLs preserved). ClickHouse is used only for read-only
product analysis; the application never writes to it.

Notes for reading the queries:

- `FINAL` is required. The replicated table is a ReplacingMergeTree, which removes duplicate row
  versions lazily during background merges. `FINAL` returns each generation's latest version.
- Money is integer nanodollars (1 USD = 1,000,000,000 nano), the same unit the app stores.
- Days are the **UTC day the generation completed**, the same bucketing as Pixelforge's `GET /v1/usage`.

## 0. Replication check

```sql
SELECT status, count() AS generations
FROM analytics.generations FINAL
GROUP BY status
ORDER BY status;
```

Result (matches the app's History page):

| status    | generations |
| :-------- | ----------: |
| failed    |           1 |
| succeeded |           8 |

## 1. Daily economics: spend, provider cost and profit

```sql
SELECT toDate(completed_at)                      AS day,
       count()                                   AS generations,
       sum(charge_nano)                          AS spend_nano,
       sum(provider_cost_nano)                   AS cost_nano,
       sum(charge_nano) - sum(provider_cost_nano) AS profit_nano
FROM analytics.generations FINAL
WHERE status = 'succeeded'
GROUP BY day
ORDER BY day;
```

Result:

|        day | generations | spend_nano | cost_nano | profit_nano |
| ---------: | ----------: | ---------: | --------: | ----------: |
| 2026-11-18 |           8 |  768515625 | 614812500 |   153703125 |

`cost_nano` per day equals Pixelforge's `cost_usd` for the production key (checked against `GET /v1/usage`).

## 2. Failure rate by quality and size

```sql
SELECT quality,
       size,
       count()                               AS finished,
       countIf(status = 'failed')            AS failed,
       round(100 * failed / finished, 1)     AS failure_pct
FROM analytics.generations FINAL
WHERE status IN ('succeeded', 'failed')
GROUP BY quality, size
ORDER BY quality, size;
```

Result:

| quality | size      | finished | failed | failure_pct |
| :------ | :-------- | -------: | -----: | ----------: |
| high    | 1024x1024 |        1 |      0 |           0 |
| medium  | 1024x1024 |        8 |      1 |        12.5 |

## 3. Processing time by quality (p50 and p90, seconds)

```sql
SELECT quality,
       count()                                                               AS generations,
       quantiles(0.5, 0.9)(dateDiff('second', started_at, completed_at))     AS p50_p90_seconds
FROM analytics.generations FINAL
WHERE status = 'succeeded'
  AND started_at IS NOT NULL
GROUP BY quality
ORDER BY quality;
```

Result:

| quality | generations | p50_p90_seconds |
| :------ | ----------: | :-------------- |
| high    |           1 | [14,14]         |
| medium  |           7 | [70,87]         |

## 4. How accurate is the pre-submit estimate?

`position_in_range` is where the actual charge landed inside the range shown to the user:
0 means it was charged the minimum, 1 the maximum. `above_max` must be 0: the maximum shown on the
Generate button is a promise.

```sql
SELECT quality,
       count() AS generations,
       round(avg((charge_nano - estimated_charge_min_nano)
                 / (estimated_charge_max_nano - estimated_charge_min_nano)), 2) AS position_in_range,
       countIf(charge_nano > estimated_charge_max_nano)                         AS above_max
FROM analytics.generations FINAL
WHERE status = 'succeeded'
GROUP BY quality
ORDER BY quality;
```

Result:

| quality | generations | position_in_range | above_max |
| :------ | ----------: | ----------------: | --------: |
| high    |           1 |              0.14 |         0 |
| medium  |           7 |              0.49 |         0 |

## Next steps

- Copy webhook_events as well, to analyse out-of-order arrivals and retries (duplicates are discarded on arrival, so they never reach the table).
- Read these numbers in the admin report through the `pg_clickhouse` extension, so the report
  queries ClickHouse from Postgres without a second database connection in the app.
