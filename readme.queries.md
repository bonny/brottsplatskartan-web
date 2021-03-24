## Användbara SQL-frågor för statistik osv.

```sql

# n mest lästa händelserna
select
year(created_at) as createdYear, created_at, crime_event_id,
count(crime_event_id) as crimeEventIdCount
from crime_views
where created_at <= '2020-01-01'
and created_at >= '2019-01-01'
group by crime_event_id
order by crimeEventIdCount DESC
limit 10

```

# Totalt antal händelser 2020

```sql

select
	count(id) from crime_events
where
	created_at < '2021-01-01'
	and created_at >= '2020-01-01'

```
