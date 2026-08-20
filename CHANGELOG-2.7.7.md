# PSC LMS 2.7.7 — Student Synchronization Fix

- Dashboard Students count now counts `wp_psc_student_profiles`, not all WordPress users.
- Existing subscriber accounts are backfilled into the student profile table during database upgrade.
- Students admin page reads the same profile records used for the dashboard.
- Student search includes WordPress name/email and profile name/phone.
- Existing View / Edit profile workflow retained.
