# PSC Learning Platform 2.7.0

Complete WordPress backend for the PSC LMS.

### AI PDF Question Import
The Question Bank importer now has an optional **Use AI extraction** mode. It renders PDF pages in the browser and sends page images to OpenAI from the WordPress server. This is designed for difficult Malayalam/English PDFs where the PDF text layer or OCR option labels are unreliable.

AI extraction rules:
- Start at the first real numbered question.
- Ignore instructions and cover material before Question 1.
- Extract visible question text and options only.
- Assign A/B/C/D/E by option position, not by corrupted labels.
- Do not infer or set correct answers.
- Keep imported questions editable.

Configure the API key at **PSC LMS → Settings → AI**. API usage is billed separately by OpenAI.

The browser OCR/text extraction remains available when AI mode is not selected.


### Question Bank multi-select
The Question Bank now supports selecting multiple questions with checkboxes, Select All, bulk adding selected questions to an exam, and bulk deletion.


### PDF / DOCX Question Import
Upload text-based PDF or DOCX Word files. PDF extraction uses the embedded text layer by default; OCR and AI are optional. PSC two-column option markers are normalized by position, and imports are blocked when a document explicitly declares 100 questions but fewer are detected.
