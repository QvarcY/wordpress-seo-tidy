CREATE TABLE IF NOT EXISTS submissions (
  id TEXT PRIMARY KEY,
  host TEXT NOT NULL,
  name TEXT NOT NULL,
  url TEXT NOT NULL,
  description TEXT NOT NULL,
  owner_hash TEXT NOT NULL,
  challenge TEXT NOT NULL,
  status TEXT NOT NULL CHECK (status IN ('pending','verified','approved','rejected')),
  consent_at TEXT NOT NULL,
  verified_at TEXT,
  approved_at TEXT
);
CREATE UNIQUE INDEX IF NOT EXISTS one_active_domain
  ON submissions(host) WHERE status != 'rejected';
CREATE INDEX IF NOT EXISTS public_sites
  ON submissions(status, approved_at DESC);
