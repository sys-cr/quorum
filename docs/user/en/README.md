# Quorum — User Documentation

Quorum is an audience-response system (software clicker) for Stud.IP 6: instructors launch live polls, students answer anonymously by scanning a QR code with their phone — no Stud.IP login required.

The complete manual lives in **one** file with a table of contents: **[manual.md](manual.md)**. The table below jumps straight to the relevant section.

> **The same manual is built right into Quorum** — it is served from this very `manual.md`, so there is no second place to maintain:
> - **Instructors:** in the **help bar** on the right of every Quorum page, including a **PDF download** of the complete manual.
> - **Students:** via **"Download the manual"** in the footer of the voting page (the participant section as a PDF).

## Where would you like to start?

| I want to … | Section |
|---|---|
| … run polls in **my course** or on the workplace | [Where to find Quorum](manual.md#einstieg) |
| … organise polls in **collections** | [Collections](manual.md#sammlungen) |
| … **present** in the lecture hall (full screen, keyboard control, question by question) | [Presenter (projector)](manual.md#presenter) |
| … get the **QR code** onto the projector | [QR code & short URL](manual.md#qr-code) |
| … know what **participants** see on their phones | [Participating (for students)](manual.md#teilnehmen) |
| … run a **quiz** with points and a leaderboard | [Quiz mode](manual.md#quiz) |
| … view **results**, moderate free-text answers, export as CSV/PDF | [Results, moderation & export](manual.md#ergebnisse) |
| … **install, configure, migrate** Quorum (administration) | [Installation](../../install/en/README.md) · [Administration](../../admin/en/README.md) |

## Your first poll in 30 seconds

1. In your course, open the **"Quorum"** tab.
2. Create a **question** with answer options and click **Start poll**.
3. Show the **QR code** (full screen for the projector). Students scan, answer anonymously, done.
4. Watch the votes come in **live** and click **End poll** once you have enough answers.

## Good to know

- **Anonymous, no login:** students do not need a Stud.IP account — just a device with a camera. Duplicate-vote protection is stored purely in the browser; there is no IP tracking.
- **Live sync:** one scan is enough — the participant page waits for the start and, with collections, automatically follows to the next question.
- **Mobile and accessible:** everything works from 375 px screen width, with keyboard and screen reader, in dark and high-contrast mode.
- **Several question types:** single choice, multiple choice, scale/Likert, emoji, free text, matrix — optionally with a time limit and countdown.
- **Quiz mode:** points for correct (and fast) answers, a pseudonymous leaderboard with strictly voluntary participation.
- **Peer instruction:** ask the same question in two rounds and compare them with a delta table.
- **Results and export:** a results page per poll, CSV and PDF export, re-importable JSON definition; two-stage free-text moderation.
- **Demo content:** the help bar on the workplace page can load a sample collection with sample answers.
- **No external services:** no data is sent to third parties; everything runs on your Stud.IP server.

The German version of the user documentation lives at [`../de/`](../de/README.md).
