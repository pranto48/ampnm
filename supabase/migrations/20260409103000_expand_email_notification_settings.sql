-- Expand SMTP configuration options for more granular email notification control
ALTER TABLE public.smtp_settings
  ADD COLUMN IF NOT EXISTS smtp_reply_to_email TEXT,
  ADD COLUMN IF NOT EXISTS smtp_subject_prefix TEXT DEFAULT '[AMPNM]',
  ADD COLUMN IF NOT EXISTS smtp_connection_timeout_seconds INTEGER DEFAULT 30,
  ADD COLUMN IF NOT EXISTS smtp_max_emails_per_hour INTEGER DEFAULT 240,
  ADD COLUMN IF NOT EXISTS smtp_allow_invalid_certs BOOLEAN DEFAULT false;
