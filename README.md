# HelpAI Block for Moodle

A Moodle 4.5+ block (`block_helpai`) that lets students ask questions about the PDF resources in **this course**. The site administrator pastes an OpenAI API key; the institution pays OpenAI. There is no author-side billing, licence server, Stripe integration, or phone-home.

**Owner:** Sergio Comerón  
**Release:** 1.5.1 (MATURITY_BETA)

---

## Bring your own key (BYOK)

HelpAI uses a **site-level** OpenAI key stored in plugin settings (`block_helpai/openai_apikey`).

- The Moodle site (the institution) owns the key and pays OpenAI.
- The key is stored as a password-style admin setting (`admin_setting_configpasswordunmask`).
- The plugin never logs the key.
- This plugin does **not** sell licences, meter usage for the author, or send telemetry.

Configure it at: **Site administration → Plugins → Blocks → HelpAI**.

---

## Daily limit

Students are limited to a number of questions **per user, per course, per day**.

- Admin setting: **Daily questions per student** (`block_helpai/dailylimit`).
- Default: **20**. Set to **0** for no limit.
- Enforced on the server **before** OpenAI is called.
- Hitting the cap returns a translated error (English and Spanish).
- **Teachers, editing teachers and managers are not subject to this cap.** Anyone with `block/helpai:viewhistory` is exempt. That is the Moodle-idiomatic option: the same roles that can read the course question log are not blocked as students.

The day boundary uses the current user’s midnight (`usergetmidnight`).

---

## Question log (teacher view)

Every ask is stored in `block_helpai_questions`:

- course, user, question, student-facing answer/summary, timestamp
- whether AI was used
- outcome: answered, not in materials, daily limit, error, or no PDFs

Raw OpenAI request/response payloads are **not** stored.

Teachers open the log from the block (**Question log**) if they have `block/helpai:viewhistory` (editing teacher, teacher, manager). The report lists questions, filterable by user.

Students still have a personal chat history (`block_helpai_history`) they can clear. Clearing chat does **not** delete the teacher log. GDPR export/delete covers both tables.

---

## Stay inside the course

- Only PDF **resource** files in the course where the student is asking are considered.
- Hidden or otherwise unavailable activities are dropped (`uservisible` + `mod/resource:view`).
- PDFs from other courses are never sent to the model.
- If the course PDFs do not contain the answer, the assistant must say so and must not invent. When they do contain it, the reply points at the PDF.

---

## Features

- Chat UI for questions about course PDFs
- Two search modes:
  - **AI only** (default): attach the course PDF files to OpenAI (GPT-4o / GPT-4 Turbo). No `pdftotext` required. If the model rejects file input, falls back to extracted text.
  - **Hybrid**: local keyword search first, then AI with extracted text. Requires `pdftotext` for indexing.
- PDF schemas/outlines (existing)
- Daily student cap and teacher question log (1.5.0)

## Requirements

- Moodle 4.5 or later
- A site OpenAI API key (GPT-4o or GPT-4 Turbo recommended)
- Optional, hybrid mode only: `pdftotext` (poppler-utils)

## Installation

1. Copy this directory to `blocks/helpai/`
2. Visit admin notifications to finish install / upgrade
3. Paste the site OpenAI key and set the daily limit under **Plugins → Blocks → HelpAI**
4. Turn editing on in a course and add the **HelpAI** block

### pdftotext (hybrid mode only)

```bash
# Ubuntu/Debian
sudo apt-get install poppler-utils
# CentOS/RHEL
sudo yum install poppler-utils
# macOS
brew install poppler
```

## Capabilities

| Capability | Default roles | Purpose |
|---|---|---|
| `block/helpai:addinstance` | editingteacher, manager | Add the block to a course |
| `block/helpai:myaddinstance` | user | Add to Dashboard (block is course-only in practice) |
| `block/helpai:askquestion` | student, teacher, editingteacher, manager | Ask questions |
| `block/helpai:viewhistory` | teacher, editingteacher, manager | Open the course question log; **exempt from the student daily cap** |

## Privacy

Stored in Moodle (export/delete via the privacy API):

- `block_helpai_history` — personal chat transcript
- `block_helpai_questions` — course question log

Sent to **OpenAI** (`api.openai.com`) on each ask or schema generation, using the site API key:

- the student's question (or a “outline this PDF” request)
- the PDF files the user can see in that course (as file attachments or extracted text)
- the configured model name

User id, name and email are **not** sent. Retention at OpenAI is governed by the institution's OpenAI account. The plugin does not send data to the author. This transfer is declared in the privacy metadata (`add_external_location_link`) and shown as an admin heading under **Plugins → Blocks → HelpAI**.

## Scheduled task

Daily at 02:00 the plugin indexes new course PDFs (hybrid cache). Manual run:

```bash
php admin/cli/scheduled_task.php --execute='\\block_helpai\\task\\index_pdfs'
```

## Licence

GPL v3 or later.

## Credits

Sergio Comerón, 2025–2026. Built for Moodle 4.5+.

---

# HelpAI (español)

Bloque de Moodle 4.5+ para preguntar sobre los PDF **de este curso**. El administrador del sitio pega una clave de OpenAI; **la institución paga a OpenAI**. No hay facturación del autor, licencias, Stripe ni telemetría.

## Trae tu propia clave (BYOK)

La clave es del sitio (`block_helpai/openai_apikey`), se guarda como contraseña y no se registra en logs.

## Límite diario

Por defecto **20 preguntas por estudiante, por curso y día**. Quien tenga `block/helpai:viewhistory` (profesor / gestor) no está sujeto a ese tope.

## Registro para el profesor

Cada pregunta se guarda (curso, usuario, pregunta, respuesta mostrada, si se usó IA, resultado). El profesor abre **Registro de preguntas** desde el bloque. No se guardan las peticiones crudas a la API.

## Dentro del curso

Solo se usan PDF que el estudiante puede ver en ese curso. Si los materiales no contienen la respuesta, el asistente lo dice y no inventa.

## Privacidad

En Moodle se guardan el chat personal y el registro de preguntas. A OpenAI se envían la pregunta y los PDF visibles del curso (no el id, nombre ni correo del usuario). La retención en OpenAI la marca la cuenta de la institución.
