# Quorum

**Language:** [Deutsch](README.md) · English

Stud.IP 6 plugin: audience response system (software clicker). Lecturers start a live poll in seconds; students answer anonymously by QR code from their smartphone — without a Stud.IP login.

## Documentation

- **[Installation guide](docs/install/en/README.md)** — set up from scratch in a Stud.IP 6 instance (incl. Cliqr migration).
- **[Operations & administration](docs/admin/en/README.md)** — configuration, maintenance, runtime topology and data storage.
- **[User manual](docs/user/en/README.md)** — usage for lecturers and students.
- **[Developer guide](docs/developer/en/README.md)** — architecture, layers, directory layout, build; contributing via [`CONTRIBUTING.md`](CONTRIBUTING.md).

German versions are under `docs/<area>/de/` (e.g. [`docs/install/de/`](docs/install/de/README.md)).

## What Quorum offers

- **Anonymous polls via QR code** — students scan, answer, done. No Stud.IP account needed. The double-vote guard is stored purely in the browser — no IP tracking.
- **Question types** — multiple choice (single and multiple select), scale (Likert), emoji reaction, free text (word cloud) and matrix; optionally with a **time limit** (countdown on the participant page, automatic stop).
- **Live sync for participants** — the vote page waits before the start and switches to the question on its own as soon as voting begins; within collections the phone follows automatically to the next started question. One scan is enough.
- **Collections with flow control** — "Start voting — all questions" (students click through themselves) or "Start voting — question by question" (the lecturer advances); create new polls directly inside the collection; archive with reactivation and permanent deletion.
- **Presenter** — full-screen view for the projector with keyboard control (←/→ switch question, space to start/stop voting, N next question, L leaderboard, F full screen, Q QR code, Esc to exit).
- **Quiz mode** — mark correct answers, the server computes the points (correct = points, faster = more points when a time limit is set); pseudonymous leaderboard with strictly voluntary participation via a freely chosen nickname — live on the phone and in the presenter.
- **Results & export** — a results page per poll (option/votes/percentage, free-text list, matrix table), CSV and PDF export for all question types, re-importable JSON definition.
- **Free-text moderation** — an admin blocklist filters before saving; lecturers can remove individual answers after the fact.
- **Comparison rounds (peer instruction)** — ask the same question across several rounds and compare them side by side with percentage-point deltas; running follow-up rounds hide earlier results from participants.
- **Demo content** — load a sample collection with sample answers via the Stud.IP help bar on the workplace page.
- **Stud.IP 6 native** — uses the `ShortUrl` API from 6.2 on (poll short links appear automatically under "Workplace → My short links"); backward compatible with 6.0/6.1 via its own table and an automatic adapter.
- **Mobile-first** — fully usable from 375 px (typical smartphone portrait).
- **Accessible** — WCAG 2.2 AA; tested with axe-core to 0 violations.
- **No external services** — no data transfer to third parties; all assets self-hosted.

## Origin

Quorum builds on the Stud.IP plugin ["cliqr"](https://github.com/elan-ev/CliqrPlugin) by [elan e. V.](https://elan-ev.de) and carries its idea — an audience response system directly inside Stud.IP — forward in a modernized form. Many thanks for the groundwork!

## License

GNU GPL-3.0-or-later **with additional terms under section 7** — see [`LICENSE`](LICENSE) and [`SUPPLEMENTAL-TERMS.txt`](SUPPLEMENTAL-TERMS.txt).

The additional terms require the graphical interface to visibly name the author, the source-code repository and the license. Quorum satisfies this via the Stud.IP help bar (Helpbar) on every plugin page.

## Author

Developed by **Bodo Steffen** — source code: <https://github.com/sys-cr/quorum>
