-- Created automatically on first run. Times are stored in UTC.

CREATE TABLE IF NOT EXISTS events (
    id               INTEGER PRIMARY KEY,
    slug             TEXT    NOT NULL UNIQUE,
    title            TEXT    NOT NULL,
    mode             TEXT    NOT NULL CHECK (mode IN ('dates', 'weekdays')),
    days             TEXT    NOT NULL,           -- JSON: ISO dates, or weekday numbers 0-6
    start_minute     INTEGER NOT NULL,           -- minutes after midnight, event zone
    end_minute       INTEGER NOT NULL,
    slot_minutes     INTEGER NOT NULL CHECK (slot_minutes IN (15, 30)),
    timezone         TEXT    NOT NULL,           -- IANA name
    theme            TEXT,                       -- NULL = site default
    admin_token_hash TEXT    NOT NULL,
    created_at       TEXT    NOT NULL,
    expires_at       TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS responses (
    id            INTEGER PRIMARY KEY,
    event_id      INTEGER NOT NULL REFERENCES events (id) ON DELETE CASCADE,
    name          TEXT    NOT NULL COLLATE NOCASE,
    password_hash TEXT,
    slots         TEXT    NOT NULL DEFAULT '[]', -- JSON: UTC epoch seconds, or "weekday:minute"
    updated_at    TEXT    NOT NULL,
    UNIQUE (event_id, name)
);

-- Bookkeeping for rate limits (event creation, failed sign-ins). No personal
-- data: the bucket is a keyed hash of the address.
CREATE TABLE IF NOT EXISTS rate_limits (
    bucket TEXT    NOT NULL,
    at     INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS rate_limits_bucket ON rate_limits (bucket, at);
CREATE INDEX IF NOT EXISTS events_expires ON events (expires_at);
