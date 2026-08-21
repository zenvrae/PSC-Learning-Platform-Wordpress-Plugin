# PSC LMS 3.1.8

## JSON import critical-error hotfix

- Fixed a PHP runtime fatal error on the JSON import screen caused by a Python-style `None` literal in generated PHP.
- JSON import page now renders normally.
- JSON import accepts UTF-8 JSON arrays, optional correct answers, Malayalam Unicode, and the existing question schema.
- Existing pagination and bulk-delete features remain unchanged.

# PSC LMS 3.1.7

## Question bank improvements

- Added WordPress admin JSON question import for UTF-8/Unicode question arrays.
- Supports `question`, `question_text`, `options`, `correct_answer`, `correct`, `explanation`, `source`, `question_number`, `number`, and `exam_year`.
- `correct_answer: null` is allowed; imported questions remain without a correct answer for later admin selection.
- Added paginated Question Bank (25 questions per page).
- Existing bulk delete is retained and securely deletes selected questions and related options/facts/exam links.
- JSON imports are capped at 5,000 questions and 10 MB per file.
- UTF-8 BOM is handled and Unicode Malayalam is preserved.

# PSC LMS 3.1.6

## Onboarding profile save reliability

- `/me/profile` explicitly validates the onboarding token for new-student creation.
- Missing and invalid/expired onboarding tokens now return distinct, clear 403 errors.
- Existing students continue to update without an onboarding token.
- Student creation remains restricted to explicit onboarding submission.
- No student record is created merely by authentication or opening onboarding.
- Existing v3.1.5 phone normalization remains active for onboarding and profile updates.
- Frontend payload aliases (`name`, `phone`, `district`, `qualification`, `targetExam`, `exam`, `dob`, `age`, `onboarding_token`) remain supported.

# PSC LMS 3.1.5

## Phone normalization

- Normalizes Indian phone numbers during both new-student onboarding and existing-student profile updates.
- Accepts 10-digit numbers and common `91` / `+91` / `0091` formats.
- Stores the canonical 10-digit number in the `phone` column.
- Validates that the canonical number starts with 6-9.
- No database schema change is required.

# PSC LMS 3.1.4

## Minimal student existence endpoint

- Added `GET /wp-json/psc/v1/me/student-exists`.
- Requires valid Firebase authentication.
- Uses the authenticated Firebase user's email to check the WordPress `students` table.
- Returns only `{ "student_exists": true|false }`.
- Does not return student profile data, IDs, email, status, courses, or other fields.
- Does not create or update a student record.
- Existing student lookup is case/whitespace normalized.
- Existing authentication and student-management endpoints remain unchanged.

# PSC LMS 3.1.3

## Firebase REST authentication and student-status routing fix

- Added a dedicated `require_authenticated_firebase` permission callback.
- `/me/student-status` now requires only a valid Firebase identity; it no longer requires an existing/active student before the endpoint can report student status.
- `/me/onboarding/start` now requires only a valid Firebase identity; a missing student is a valid onboarding state.
- `/me/profile` now authenticates the Firebase user first; new-student creation still requires the one-time onboarding token, while existing students may update their profile.
- Removed the access-status gate from the permission callback for these identity/profile endpoints.
- Firebase bearer-token extraction now supports `Authorization`, `REDIRECT_HTTP_AUTHORIZATION`, and `X-Firebase-ID-Token` (plus `getallheaders()` variants), improving compatibility with reverse proxies/Wasmer.
- A failed Firebase authentication returns HTTP 401 and is never treated as "student does not exist".
- Student lookup remains read-only during authentication/status checks.
- No frontend/MySQL direct database query is introduced.
- Student creation remains restricted to explicit profile submission with a valid onboarding token.
- Removed students remain blocked from profile updates.
