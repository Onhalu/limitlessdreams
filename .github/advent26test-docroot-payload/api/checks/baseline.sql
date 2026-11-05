-- Pre-delta schema for the isolated migration check. Not applied to Supabase.
CREATE SCHEMA IF NOT EXISTS auth;

CREATE TABLE IF NOT EXISTS auth.users (
    id uuid PRIMARY KEY
);

CREATE TABLE IF NOT EXISTS public.stocks (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    ticker text NOT NULL UNIQUE CHECK (ticker ~ '^[A-Z0-9.\-]{1,12}$'),
    company_name text NOT NULL,
    exchange text NOT NULL,
    currency text NOT NULL,
    sector text NOT NULL,
    industry text NOT NULL,
    description_cs text NOT NULL,
    interesting_reason_cs text NOT NULL,
    is_demo boolean NOT NULL DEFAULT false
);

CREATE TABLE IF NOT EXISTS public.advent_calendar (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    year integer NOT NULL,
    day_number integer NOT NULL CHECK (day_number >= 1 AND day_number <= 24),
    stock_id uuid NOT NULL REFERENCES public.stocks (id),
    unlock_date date NOT NULL,
    UNIQUE (year, day_number)
);

CREATE TABLE IF NOT EXISTS public.advent_opens (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL REFERENCES auth.users (id),
    calendar_id uuid NOT NULL REFERENCES public.advent_calendar (id),
    opened_at timestamptz NOT NULL DEFAULT now(),
    purchase_confirmed boolean NOT NULL DEFAULT false,
    purchase_confirmed_at timestamptz,
    UNIQUE (user_id, calendar_id),
    CHECK (
        (purchase_confirmed = false AND purchase_confirmed_at IS NULL)
        OR (purchase_confirmed = true AND purchase_confirmed_at IS NOT NULL)
    )
);

INSERT INTO public.stocks (
    id, ticker, company_name, exchange, currency, sector, industry,
    description_cs, interesting_reason_cs, is_demo
) VALUES (
    'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    'AAPL',
    'Demo Co',
    'XNAS',
    'USD',
    'demo',
    'demo',
    'demo',
    'demo',
    true
);

INSERT INTO public.advent_calendar (year, day_number, stock_id, unlock_date)
SELECT 2026, day_number, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', make_date(2026, 12, day_number)
FROM generate_series(1, 24) AS day_number;

INSERT INTO auth.users (id) VALUES ('11111111-1111-4111-8111-111111111111');
