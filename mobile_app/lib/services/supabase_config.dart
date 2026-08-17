// Hardcoded to match this project's config/.env — no server URL prompt in the app.
//
// SECURITY NOTE: this is the Supabase *service_role* key, copied from the web
// app's .env. It bypasses Row Level Security and grants full read/write access
// to every table. Embedding it in a distributed mobile app means anyone who
// installs the app can extract this key from the APK and get full database
// access. This was a deliberate, explicitly-confirmed tradeoff for this app —
// if that ever changes, replace this with a restricted anon key + RLS
// policies (or route through the /api/ PHP backend instead).
const String supabaseUrl = 'https://ayljlxcqdrbeobkomxfj.supabase.co';
const String supabaseServiceKey =
    'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImF5bGpseGNxZHJiZW9ia29teGZqIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc4NDUzMDk5NiwiZXhwIjoyMTAwMTA2OTk2fQ.5U-PiXuF5M0m2XYldGtQBQNNTE7GHm7haEznDneg9hI';
