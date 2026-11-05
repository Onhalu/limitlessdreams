-- Assert shape only. Do not select task title or body text.
DO $$
DECLARE
    task_days integer;
    stock_days integer;
    task_list integer[];
    snap text;
BEGIN
    SELECT count(*) INTO task_days
    FROM public.advent_calendar
    WHERE year = 2026 AND day_type = 'task';

    SELECT count(*) INTO stock_days
    FROM public.advent_calendar
    WHERE year = 2026 AND day_type = 'stock';

    IF task_days <> 7 OR stock_days <> 17 THEN
        RAISE EXCEPTION 'day type counts % / %', task_days, stock_days;
    END IF;

    SELECT array_agg(day_number ORDER BY day_number) INTO task_list
    FROM public.advent_calendar
    WHERE year = 2026 AND day_type = 'task';

    IF task_list <> ARRAY[5, 6, 12, 13, 19, 20, 24]::integer[] THEN
        RAISE EXCEPTION 'task day numbers mismatch';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM public.advent_calendar
        WHERE year = 2026
          AND day_type = 'task'
          AND (
              stock_id IS NOT NULL
              OR task_title IS NULL
              OR task_body IS NULL
              OR length(btrim(task_title)) = 0
              OR length(btrim(task_body)) = 0
          )
    ) THEN
        RAISE EXCEPTION 'task row shape';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM public.advent_calendar
        WHERE year = 2026
          AND day_type = 'stock'
          AND (stock_id IS NULL OR task_title IS NOT NULL OR task_body IS NOT NULL)
    ) THEN
        RAISE EXCEPTION 'stock row shape';
    END IF;
END $$;

DO $$
BEGIN
    INSERT INTO public.advent_calendar (year, day_number, day_type, stock_id, unlock_date)
    VALUES (2027, 1, 'task', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', DATE '2027-12-01');
    RAISE EXCEPTION 'task row accepted a stock id';
EXCEPTION
    WHEN check_violation THEN
        NULL;
END $$;

DO $$
BEGIN
    INSERT INTO public.advent_calendar (year, day_number, day_type, stock_id, task_title, task_body, unlock_date)
    VALUES (2027, 2, 'stock', NULL, NULL, NULL, DATE '2027-12-02');
    RAISE EXCEPTION 'stock row accepted a null stock id';
EXCEPTION
    WHEN check_violation OR not_null_violation THEN
        NULL;
END $$;

DO $$
DECLARE
    cal_id uuid;
    kept text;
BEGIN
    SELECT id INTO cal_id
    FROM public.advent_calendar
    WHERE year = 2026 AND day_number = 5;

    INSERT INTO public.advent_opens (
        user_id, calendar_id, day_type, task_title, task_body, reveal
    ) VALUES (
        '11111111-1111-4111-8111-111111111111',
        cal_id,
        'task',
        'fixture-title',
        'fixture-body',
        jsonb_build_object('dayType', 'task', 'title', 'fixture-title', 'body', 'fixture-body')
    );

    UPDATE public.advent_calendar
    SET task_title = 'changed-title',
        task_body = 'changed-body'
    WHERE id = cal_id;

    SELECT o.task_title INTO kept
    FROM public.advent_opens AS o
    WHERE o.calendar_id = cal_id;

    IF kept IS DISTINCT FROM 'fixture-title' THEN
        RAISE EXCEPTION 'snapshot mismatch';
    END IF;
END $$;

-- Restore the seeded task row after the snapshot probe rewrote it.
-- The probe used fixture strings only. Re-applying the seed statement is not
-- done here; the check database is disposable. Confirm the open row stayed put.
SELECT count(*) AS snapshot_rows
FROM public.advent_opens
WHERE day_type = 'task'
  AND task_title = 'fixture-title'
  AND reveal ->> 'title' = 'fixture-title';
