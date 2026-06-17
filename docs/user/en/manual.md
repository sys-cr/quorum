# Quorum — Manual

Quorum is the audience-response plugin for Stud.IP: teachers launch a live poll, students answer it anonymously by scanning a QR code — no app, no Stud.IP login. Answers appear live and can be presented on a projector, compared across rounds, run as a quiz and exported afterwards.

## Contents

- [Participating (for students)](#teilnehmen)
- [Quick start](#schnellstart)
- [Where to find Quorum](#einstieg)
- [Creating polls](#anlegen)
- [Question types](#fragetypen)
- [Running a poll](#durchfuehren)
- [QR code & short URL](#qr-code)
- [Presenter (projector)](#presenter)
- [Collections](#sammlungen)
- [Peer instruction (comparing rounds)](#peer-instruction)
- [Results, moderation & export](#ergebnisse)
- [Quiz mode](#quiz)
- [Demo content](#demo)
- [Configuration](#konfiguration)
- [Privacy](#datenschutz)
- [FAQ](#faq)

<!-- AUDIENCE:STUDENT:START -->
<a id="teilnehmen"></a>
## Participating (for students)

This is what you see after scanning the QR code (or typing the short URL). **No Stud.IP login** is required — participation is anonymous and works in any smartphone browser. You do **not** need to install an app.

### The page updates itself

The vote page is **live-synced** — scanning once is enough:

- **Before the start**, the page shows "The poll has not started yet." As soon as the instructor starts the voting, the page switches **on its own** to the question — no reload, no rescan.
- **With collections**, the phone follows automatically: when the instructor starts the next question, it appears on every device by itself.

### Answering

1. Read the question, tap your answer(s).
2. **Submit.** The page confirms your answer.

Depending on the question type you tap one option (single choice), several (multiple choice), a scale value or an emoji, type a short text (free text), or rate several statements (matrix).

### Time limit and countdown

If the instructor set a **time limit**, the page shows a **countdown**. When time runs out, the poll stops automatically — no further answers are accepted.

### Duplicate-vote protection

Anyone who has already voted sees the **confirmation** instead of the question when reloading the page. This protection is stored **purely in the browser** — Quorum does **no IP tracking** and stores no personal data.

### Quiz: joining the leaderboard is voluntary

If the question runs in [quiz mode](#quiz), you can **voluntarily** join a leaderboard:

- Tick **"Join the leaderboard with a nickname (voluntary)"** and enter a **freely chosen nickname** — not your real name.
- The nickname can be **changed or deselected before every answer**.
- After submitting, the phone shows the **leaderboard live**.

**Without the tick**, the answer counts normally towards the result — but it never appears anywhere under a name.

### The "Quorum" tab in your course

Depending on your institution's setting, you may see a **"Quorum" tab** in the course. There you find:

- **Happening now:** the currently running polls with a **"Join now"** button — it opens the same anonymous vote page as the QR code (no login, no name).
- **Past results:** the results of finished polls, provided the lecturer has released them.

If you don't see a tab, take part as usual via **QR code or short URL** — both are equivalent and likewise anonymous.
<!-- AUDIENCE:STUDENT:END -->

<a id="schnellstart"></a>
## Quick start

Your first poll in about 30 seconds:

1. Open the **"Quorum"** tab in your course (or the **Quorum** tile on your workplace).
2. Click **Create poll**, type a question, pick **single choice**, add two options, **Save**.
3. Open the poll and click **Start poll**.
4. Click **Show QR code** and project it — students scan and answer anonymously.
5. Watch the votes come in live, then click **End poll**.

That's it. For evaluation later, every poll card carries a **"Results …"** action with CSV/PDF export — see [Results, moderation & export](#ergebnisse).

<a id="einstieg"></a>
## Where to find Quorum

Quorum has two entry points:

- **"Quorum" course tab** — the instructor interface directly **inside your course**. Click the **"Quorum"** tab in the course header. Use this for everything tied to one course.
- **Quorum workplace tile** — under **Mein Arbeitsplatz** ("my workplace"), the **Quorum** tile ("create and evaluate live polls") opens a full-page overview of all your polls. Use this to manage polls **independently of a course** — a guest lecture, a tutorial spanning several courses, or reusable question templates.

For course-bound polls, the workplace overview links directly to the "Quorum" tab of the respective course.

**Roles:** Inside a course, the "Quorum" tab requires at least **tutor rights**. The workplace tile and course-independent management appear from the configured **minimum role** (default: **lecturer**) — see [Configuration](#konfiguration). If the tile is missing, your role probably does not meet that minimum; ask your Stud.IP administration.

Both surfaces run inside the normal Stud.IP frame, work on any device from 375 pixels wide (tables stack into cards on a phone), follow Stud.IP's **language** and **high-contrast mode** (Profile → Settings → Accessibility), and are fully keyboard- and screen-reader-operable. Quorum deliberately does **not** follow the OS dark mode — it stays light like the Stud.IP frame instead of going dark on its own. The anonymous vote page additionally responds to the system settings `prefers-contrast` and `forced-colors`. Status is never conveyed by colour alone. The coloured card accent encodes the **question type** (single choice, multiple choice, scale, emoji, free text); collections carry their own neutral accent.

<a id="anlegen"></a>
## Creating polls

1. Click **Create poll**.
2. Enter the **question** (plain text; line breaks are preserved).
3. Choose the **question type** — see [Question types](#fragetypen). (The matrix type is available via the course tab.)
4. Depending on the type: for **single/multiple choice** at least **two options** (add more via **"+ Add option"**, up to 20); for **Scale** numeric or named steps; for **Emoji** pick a set via **"Template"** (all editable — see [Question types](#fragetypen)).
5. Optional: set a **time limit in minutes**. Participants see a countdown and the poll stops automatically when time runs out.
6. Optional (single/multiple choice): mark the **correct answer(s)** — they are highlighted in the results. For a scored **quiz with leaderboard** also enable **"Quiz mode"** (single choice only; see [Quiz mode](#quiz)).
7. Optional: bind the poll to a **course**. Leave the field empty and it stays course-independent (and can be bound later via **Edit**).
8. **Save.** The poll appears in the overview as a not-yet-running template.

A missing question or fewer than two options is rejected server-side; you get the form back with your input and an error message.

<a id="fragetypen"></a>
## Question types

| Type | What for |
|---|---|
| **Single choice** | exactly one answer out of several options |
| **Multiple choice** | several answers selectable at once |
| **Scale (Likert)** | assessment on a scale — numeric or with named steps |
| **Emoji reaction** | a quick mood check via emojis |
| **Free text** | open answers in the participants' own words (anonymous) |
| **Matrix** | rate several statements together on one scale (created via the course tab only) |

All texts (question and options) are plain text; line breaks are preserved.

### Answer options — dynamic (single/multiple choice, emoji)

Choice questions start with **two** option fields; **"+ Add option"** adds more (up to **20**), the **✕** removes one again (at least two remain). Empty fields are ignored on save.

### Scale — numeric or named steps

For the **Scale** type you first pick the **scale type**:

- **Numeric (1 … N):** you only set the **number of points** (2 to 6 — e.g. 6 for German school grades 1–6). Quorum generates the steps "1" … "N" automatically.
- **Named steps:** you name the steps yourself (highest first). **"Template"** fills typical sets with one click — freely editable afterwards:
  - **Agreement (5-/4-/3-point):** agree … disagree
  - **Frequency (5-point):** always · often · sometimes · rarely · never

### Emoji — templates and free

For the **Emoji** type you fill the options via **"Template"** (mood 3-/5-point, thumbs, understanding, more reactions) and then swap the emoji freely via **copy & paste** — your own emoji are possible at any time.

### Marking the correct answer (optional — single/multiple choice only)

For **single choice** and **multiple choice** you can optionally mark the correct answer(s) — **"Correct answer"** per option (exactly one for single choice, several for multiple choice). After the poll ends, the results highlight the correct answer with a **✓**. Marking is **optional**; only in [quiz mode](#quiz) is at least one correct answer required. All other question types (scale, emoji, free text, matrix) have no correct-answer marking.

<a id="durchfuehren"></a>
## Running a poll

1. Open the poll and click **Start poll**.
2. Show the **QR code** ("Show QR code" button — full screen for the projector) or the **short URL** in the header. Students scan and answer anonymously, without any Stud.IP login.
3. The votes appear **live** — the display updates within about two seconds of each new answer.
4. Click **End poll** once you have enough answers. Ended polls are frozen.

The participant page is **live-synced**: anyone who scans before the start sees "The poll has not started yet." and gets the question automatically as soon as you start — nobody has to reload or rescan (see [Participating](#teilnehmen)). The QR code dialog is described in [QR code & short URL](#qr-code).

Results are shown as a bar, donut or bubble chart depending on the question type, with a colour-distinguished, readable display; charts load on demand so the page stays fast. For evaluation after the session, use the **"Results …"** action — see [Results, moderation & export](#ergebnisse).

<a id="qr-code"></a>
## QR code & short URL

Display the QR code for a running poll in a large dialog and project it, so students at the back of the room can scan and vote.

1. Start a poll.
2. Click **Show QR code** — the dialog opens with a large QR code.
3. Project the window on a beamer or share your screen.

One scan lasts the whole session: the participant page is live-synced — it waits before the start and, with collections, automatically follows to the next started question (see [Participating](#teilnehmen)). In the [presenter](#presenter), the large QR code is also available via the **Q** key.

### Functions

| Function | Description |
|---|---|
| **Full screen** | Maximises the QR code to fill the entire display — ideal for beamer sharing |
| **Download SVG** | Saves the QR code as a scalable vector graphic (for print materials) |
| **Download PNG** | Saves the QR code as a raster image (for presentations) |
| **Close / Esc** | Closes the dialog |

### Keyboard

- **Tab** — navigates between all buttons in the dialog
- **Esc** — closes the dialog; focus returns to the trigger button automatically

### Short URL

If a URL shortener is configured in Stud.IP, the dialog shows the **short URL** next to the QR code — making it easier for students without a camera to type it manually. From Stud.IP 6.2, short links are managed natively (see [Configuration](#konfiguration)).

<a id="presenter"></a>
## Presenter (projector)

The presenter is Quorum's clean full-screen view for the projector: question, QR code and live results large on a single page — no editing screens, no sidebar. The controls are standard Stud.IP buttons; everything can additionally be driven from the keyboard.

### Opening the presenter

- **Present a single poll** — via the poll's card or detail view.
- **Present a collection** — walks through the polls of a collection in order; use ←/→ to switch between questions.

### Keyboard control

| Key | Action |
|---|---|
| **←** / **→** | show previous / next question |
| **Space** | start / end voting for the current question |
| **N** | start the next question (question-by-question flow) |
| **L** | toggle the leaderboard (quiz mode only, see [Quiz mode](#quiz)) |
| **F** | toggle full screen |
| **Q** | show the QR code large |
| **Esc** | exit the presenter |

Every action is also available as a button — the keys are a shortcut, not a requirement.

### Question by question

If you started a collection with **"Start voting — question by question"**, you advance with **"Start next question"** (button or **N** key). The participants' phones follow automatically to the question just started — nobody has to rescan.

### Projector tips

- **F** puts the presenter into the browser's full-screen mode — ideal right after switching to the projector.
- **Q** shows the QR code filling the screen while participants are still joining; pressing it again returns to the question.
- The live results update automatically while the poll is running.

<a id="sammlungen"></a>
## Collections

Collections bundle several polls, for example as reusable question sets for a lecture. You reach them via the **"Collections" sidebar view** — both on the workplace page and in the course's **"Quorum" tab**.

- **Create / edit:** via **Collections → Create new collection**. Name and description are plain text. Like individual polls, a collection can optionally be linked to a **course** (automatically in the course tab, via the course field on the workplace page). A course-linked collection appears in the course tab — for teachers to manage, and for students to join running questions and view released results. The collection's course link is **independent** of how its individual questions are assigned.
- **Co-teaching:** All teachers (tutor/lecturer) of a course may control that course's polls and collections — not only whoever created them. Course-independent content stays with the person who created it.
- **Assign polls:** choose **"Add to collection …"** on a poll card — or click **"Create new poll"** directly on the collection's detail page; the new poll lands in the collection right away.
- **Reorder:** rearrange the polls within a collection.
- **Archive / restore:** archive collections you do not currently need; they then live in the **"Archive"** sidebar view, where you can reactivate or permanently delete them.

### Running a collection — two start variants

| Variant | What happens |
|---|---|
| **Start voting — all questions** | All questions open at once; students click **themselves** through one after the other — e.g. for feedback forms at the end of a session. |
| **Start voting — question by question** | Only the first question opens; **you** advance in the [presenter](#presenter) with **"Start next question"** (or the **N** key) — the classic clicker flow in a lecture. |

**"End voting"** stops all running questions of the collection at once. In both variants the participants' phones follow automatically — nobody has to rescan.

<a id="peer-instruction"></a>
## Peer instruction (comparing rounds)

A proven teaching method: ask the same question **before and after** a discussion round and compare the answers.

1. Run the first round and **end** it.
2. In the action menu choose **"New round …"** and then **"Compare"** — Quorum creates a second round with an identical question and identical options. (Not to be confused with **"Resume voting"**, which re-activates the same poll.)
3. Let the students discuss in small groups.
4. **Start and end** the second round.
5. Click **"Show comparison"** — both rounds open side by side with a **delta table**: which answer gained or lost how many percentage points? The direction is shown by an arrow icon and a number, not just by colour.

The comparison only works between rounds of the same question; running polls must be ended first. Any number of follow-up rounds is possible (one or two is typical). While a later round is running, Quorum hides the first round's results from the students (**"blind mode"**) so they are not influenced.

<a id="ergebnisse"></a>
## Results, moderation & export

Every poll card — including in the **archive** — carries the **"Results …"** action. It opens a plain, print-friendly results page without any editing controls. During class, the [presenter](#presenter) is the better choice for the large live display; the results page is meant for evaluation afterwards.

### The results page

| Question type | Display |
|---|---|
| Single choice, multiple choice, scale, emoji | table with **option / votes / percent** |
| Free text | list of the individual (anonymous) answers |
| Matrix | table with the distribution per row |

If you marked correct answers on a **single-** or **multiple-choice** question, the results mark them with a **✓** — regardless of quiz mode.

### Downloads

| Download | Content | Where |
|---|---|---|
| **CSV export** | aggregated numbers per option or anonymous free texts — for all question types | results page |
| **PDF export** | the same results as a Stud.IP PDF, e.g. for filing in the course | results page |
| **Download definition** (JSON) | the question definition without answers — **re-importable**, e.g. for sharing with colleagues | the poll's card menu |

No export contains personal data about students.

### Free-text moderation

There are two stages for anonymous free-text answers:

1. **Admin blocklist (before saving):** the Stud.IP administration can store blocked terms under `QUORUM_FREITEXT_BLOCKLIST`; answers containing these terms are never stored in the first place (see [Configuration](#konfiguration)).
2. **Removing afterwards (by you):** on the results page, every free-text answer carries a **"Remove"** action. After a confirmation, the answer disappears from the result and from all exports. Removal is **permanent**.

This keeps the barrier for spontaneous answers low without letting individual disruptive entries ruin the projector display.

### Releasing results to students (default) or hiding them

Students see the results of finished polls in the course's **Quorum tab** — this is the **default**. To exclude an individual poll (e.g. a sensitive question), clear the **"Results visible to students in the course tab"** checkbox when **creating** or **editing** the poll. You can change this **later**, too. Participating in running polls is unaffected — it is always possible. Free-text answers you have removed do not appear in the student view either.

<a id="quiz"></a>
## Quiz mode

Quiz mode turns a single-choice question into a small competitive quiz: correct answers earn points, fast answers earn more, and anyone who wants to can appear on the leaderboard under a self-chosen nickname.

> **Correct marking ≠ quiz:** you can mark the correct answer for **single *and* multiple choice** without a quiz, too — the **results** then simply highlight it with a ✓ (no leaderboard, no points). The **scored quiz** with leaderboard, however, is intended for **single choice**.

### Setting up a quiz

1. Create a **single-choice question** (workplace or course tab).
2. Mark the correct option with **"Correct answer"**.
3. Enable the **"Activate quiz mode"** checkbox.
4. Optional: set a **time limit in minutes** — only then does answer speed factor into the points.
5. Save and start the voting as usual.

### Scoring

Points are calculated by the **server** — they cannot be manipulated on the device:

| Situation | Points |
|---|---|
| Correct answer, **with** time limit | points for being correct, **faster = more points** |
| Correct answer, **without** time limit | full points |
| Wrong answer | no points |

### Leaderboard

- **On the phone:** participants see the leaderboard **live after submitting** their answer.
- **In the presenter:** the **L** key (or the leaderboard button) toggles the leaderboard for the projector.

### Voluntary participation (privacy)

Participants appear on the leaderboard **only by their own choice**:

- On the vote page they tick **"Join the leaderboard with a nickname (voluntary)"** and choose a **nickname** — explicitly **not their real name**.
- The nickname can be **changed or deselected before every answer**.
- **Without the tick**, the answer counts normally towards the result and statistics — but it never appears anywhere under a name.

No real names, accounts or IP addresses are linked to answers; the anonymity of the poll is preserved.

<a id="demo"></a>
## Demo content

In the Stud.IP **help bar** of the workplace page you will find **"Load demo content"**: Quorum creates a sample collection with sample polls including sample answers — ideal for trying out the results page, presenter and export risk-free. The demo content lands in your **archive**, where you can delete it again at any time; a second click does not create duplicates.

<a id="konfiguration"></a>
## Configuration

These options are set by the Stud.IP administration under **Configuration → System configuration → Quorum**. Full installation details are in [Installation](../../install/en/README.md).

- **Minimum role (`QUORUM_MIN_ROLE`)** — from which Stud.IP system role the workplace tile and course-independent management are visible. Default: `dozent` (lecturer); allowed: `user`, `autor`, `tutor`, `dozent`, `admin`, `root`. Invalid values fall back to the default. Independently, the course "Quorum" tab always requires at least **tutor rights** in that course.
- **Free-text blocklist (`QUORUM_FREITEXT_BLOCKLIST`)** — comma-separated terms; anonymous free-text answers containing one of them are rejected before being stored. Default: empty (off). This is the first of the two [moderation stages](#ergebnisse).
- **Short links** — from **Stud.IP 6.2** onwards, Quorum stores poll short links in the native Stud.IP short-link management; instructors find them under **Workplace → My short links**. On 6.0/6.1 they are managed internally and can be transferred to the native management after an upgrade. Details: [Installation](../../install/en/README.md).

<a id="datenschutz"></a>
## Privacy

- Students participate **anonymously**; no Stud.IP login is required.
- Quorum does **no IP tracking** of participants and stores no personal data; duplicate-vote protection lives purely in the participant's browser.
- **No** personal data about students is ever exported — CSV/PDF exports contain only aggregated numbers or anonymous free texts.
- The **quiz leaderboard** is voluntary: participants appear only if they explicitly opt in with a self-chosen **nickname** (not their real name), which they can change or deselect before every answer. No real names, accounts or IP addresses are linked to answers.

<a id="faq"></a>
## FAQ

**Do I have to install an app?** — No, a browser is enough.

**Can the instructor see how I voted?** — No. Answers are stored anonymously; only the aggregated numbers (and anonymous free texts) are visible.

**The page says "The poll has not started yet."** — You did everything right: just wait, the question appears automatically as soon as the instructor starts.

**The Quorum tile is missing on my workplace.** — Your role probably does not meet the configured [minimum role](#konfiguration). Contact your Stud.IP administration.

**Can I bind a course-independent poll to a course later?** — Yes, via **Edit** on the poll.

**Can other instructors see my polls?** — No. The overview shows only your own polls; access is restricted server-side to you as the owner.

**Can I still export results of archived polls?** — Yes, "Results …" including CSV/PDF also works in the archive.

**Can a removed free-text answer be restored?** — No, removal is permanent — hence the confirmation prompt.

**What is the difference between the CSV export and "Download definition"?** — The CSV export contains the **answers** (aggregated or anonymous texts). The JSON definition contains only the **question with its options** and can be imported again.

**Can I mark several options as correct?** — Yes: for **multiple choice** you mark several correct answers, and the results highlight them with ✓. The scored **quiz leaderboard**, however, is intended for **single choice** with exactly one correct answer.

**Does a quiz question work without a time limit?** — Yes. Without a time limit, every correct answer earns full points regardless of speed.

**What do participants without the leaderboard tick see?** — The same question, the same flow. They simply do not appear on the leaderboard.
