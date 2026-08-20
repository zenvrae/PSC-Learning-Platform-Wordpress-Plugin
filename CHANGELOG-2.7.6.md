# PSC LMS 2.7.6 — Student Data Foundation

- Google Firebase authentication is restricted to Google provider identities.
- First Google login now creates a `psc_student_profiles` row immediately.
- Firebase UID, auth provider, display name, and Firebase email metadata are synchronized to the WordPress user/profile.
- Existing onboarding fields are never overwritten by later logins.
- `/psc/v1/me/profile` remains the canonical student profile read/write API.
- WordPress `wp_users` + `wp_psc_student_profiles` are the source of truth for student data.
- Existing admin Students page displays and edits the synchronized profile.
- Existing courses, lessons, questions, exams, progress, REST API, AI importer, PDF/Word importer and other backend functionality are preserved.
