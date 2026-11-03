# Personal-session activities and Skill Tree

## Scope

This feature adds optional activities to existing personal Learn/Swap conversations. It does not change attendance confirmation, points, hours, community study-session behavior, qualifications, or messaging permissions. The existing uncommitted community-media and meeting-link changes were preserved.

Open a personal conversation and choose **Activities**. Each teaching direction can have one activity, once a schedule has been accepted. A swap has two directions, with separate teachers, learners, and topics. Assignment freezes the instructions, passing criteria, reference link, and selected outcome descriptions. Learners submit text and optional HTTP(S) project links only after mutual completion and positive confirmed learning time. Teachers request revision or pass the activity with feedback. Earlier attempts and feedback remain available. Completed chats remain read-only for regular messages, while the activity controls continue to work. Activity assignment, submission, and review send bell notifications.

No activity uploads, deadlines, automatic grading, extra points, or predetermined exams are included.

## Topic progress

**My Progress → Skill Tree** shows approved catalog topics grouped by subject. A small suggested Python Basics → Python Functions path uses the existing catalog entries. It does not add topics or invent assessment requirements. Prerequisites are suggestions, never enrollment restrictions. Find a learning partner filters the dashboard for that topic without editing saved profile interests.

An admin opens **Approvals → Review program and topic suggestions → Manage Skill Tree learning paths** to configure descriptions, order, one suggested prerequisite, and up to ten required outcomes per topic. Same-subject and cycle checks run on the server. New approved topics need an admin to place/configure them; unconfigured topics remain available for learning and practice.

- Not started: no confirmed learning participation for that exact catalog topic.
- Practicing: confirmed learning participation; teaching does not count as learning.
- Activity passed: a teacher passed an activity, but not every current required outcome is covered, or no completion requirements exist.
- Completed: peer-reviewed passed activities cover every current admin-defined outcome.

Teachers select only the outcomes their activity assesses. Passing confirms the activity criteria and all selected outcomes were met. Outcomes are saved as immutable text/key snapshots. Description/order edits do not change completion. Adding or rewriting required outcomes makes the new requirements pending; old activity passes and feedback are retained. Broad historical subjects are not guessed into more specific topics. Topic completion is peer reviewed, not standardized certification or permission to teach.

## Backup and activation

Before editing, a private source archive and SHA-256 manifest were created under `storage/app/private/feature-backups/skill-activities-20261104/`, including the pre-existing uncommitted work. Recovery branch: `codex/backup-before-skill-activities-20261104` (committed baseline only; use the archive to preserve the pre-existing working changes).

The initial local MySQL startup/connection problem delayed activation. Once MySQL was running, `database-before.sql` was saved in the same private backup folder and verified against `database-before.sha256` before applying this feature's migration. Only the activity/Skill Tree migration was applied; unrelated pending migrations were left alone. Tests use isolated, in-memory SQLite. The source archive excludes the local spreadsheet, secrets, dependencies, generated assets, and uploads; retain the existing `.env`, APP_KEY, and upload storage.

After a verified database backup, apply `php artisan migrate --path=database/migrations/2026_11_04_000001_create_session_activities_and_topic_plans.php`, build with `npm run build -- --configLoader runner`, and run `php artisan view:cache`. The specific path leaves any unrelated pending migrations alone. The additive migration creates `topic_learning_plans`, `session_activities`, and `activity_submissions`. It leaves existing records intact.

## Reverting

Back up any new submissions first. Restore files from the pre-change archive and remove only the feature's newly added files (compare the archive manifest and the feature diff; preserve unrelated pre-existing work). Rebuild assets. Keeping the three new tables preserves activity history while the previous code runs. Roll back only `2026_11_04_000001_create_session_activities_and_topic_plans.php` if deliberately discarding all new activity submissions and learning-path configuration. Never run `migrate:fresh` or replace the whole database to undo a UI change. Nothing in this feature is pushed automatically.

## Progress Overview revision — November 4

The Weekly Goals card is replaced with a compact My Skill Tree summary showing up to three learning topics, statuses, and pending outcome counts, plus a link to the full tree. Confirmed hours, sessions, and the weekly activity chart remain. Timezone is edited through Progress settings. Stored goals and the previous goals endpoint are retained for compatibility and rollback, but are no longer shown in the interface.

Subject selection submits immediately when changed; Show topics is only a no-JavaScript fallback. Every approved subject appears, including an empty state for subjects with no approved topics yet. New topic approvals appear without tree code changes; admin configuration is still needed for prerequisites and completion outcomes.

The follow-up migration `2026_11_04_000002_retire_legacy_teaching_category.php` moves Coding into Programming and retires the placeholder category across all selectors. Existing topic IDs, qualification names, learning plans, and activity links are preserved. Other known legacy broad topics use their matching real categories; unfamiliar broad topics retain their own subject name rather than being guessed into an unrelated category. New suggestions cannot recreate the retired placeholder. This migration's down method keeps valid classifications rather than moving newer data back automatically.

A separate pre-revision source archive, manifest, and verified SQL backup are stored under `storage/app/private/feature-backups/progress-refresh-20261104/`; the committed baseline recovery branch is `codex/backup-before-progress-refresh-20261104`. Before applying the category migration, a catalog snapshot is also saved there as `catalog-before.json` for selective restoration. Revert the UI from this source archive if needed; restoring a category should use its original catalog row and topic-parent links, preserving subsequent activity and approvals. Do not replace the entire database to revert the card layout.
