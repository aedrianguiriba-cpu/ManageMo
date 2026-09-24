// Where the PHP backend lives — used ONLY for one thing: telling the server
// to send the "delivered" email after the app confirms a delivery directly
// against Supabase (Dart can't reliably speak raw SMTP, so this one action
// still goes through the web backend). Everything else in the app talks to
// Supabase directly and does not use this URL.
//
// ⚠ Update this before building for a real device / release:
//   - Android emulator hitting your local XAMPP: keep '10.0.2.2'
//   - Physical device on the same network as your XAMPP machine: use your
//     computer's LAN IP, e.g. 'http://192.168.1.23/ManageMo'
//   - Deployed site: your real domain, e.g. 'https://managemo.example.com'
const String serverBaseUrl = 'http://10.0.2.2/ManageMo';
