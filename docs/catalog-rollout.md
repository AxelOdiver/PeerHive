# Profile learning catalog

Scope: shared degree programs, subjects, and topics; profile topic selectors; multiple topic qualifications; admin catalog approvals; categorized request options; desktop profile columns. This change does not implement skill-tree progress rules or exams.

## Backup and deployment

Before the local change: recovery branch `codex/backup-before-catalog-20261103` and source archive `storage/catalog-before-20261103.zip` preserve the previous code and uncommitted work. The archive is ignored by Git. Keep the existing APP_KEY and .env.

Back up the database before applying `2026_11_03_000001_create_learning_catalog.php`. The existing `php artisan security:prepare` command creates a dated SQL backup under `storage/app/private/security-backups`; stop writes during backup. Apply only the catalog migration, build frontend assets, and restart workers. Never use migrate:fresh against the real database.

## Catalog rules

BSIT, BSCS, and BSBA and the small initial subject/topic list are starter entries, not a declaration of the final study scope. Every program can browse and learn every approved topic. New topics must use an approved parent subject before admin approval. Catalog approval makes the entry available to all members, but does not approve any teacher. A topic name identifies the same concept throughout the catalog; duplicate names are reused instead of creating separate per-program copies.

An unlisted program can be saved on a user's profile immediately and is submitted for catalog review. Topic suggestions remain unavailable for selection until approved. Suggestions can be reviewed from Approvals → Review program and topic suggestions. Qualification proof review stays on its existing page.

Existing degree text, skills, interests, qualifications, requests, messages, and sessions are preserved. Existing qualification names are imported as catalog topics under Existing teaching subjects where they are not already in the initial catalog. An approved Coding qualification continues to permit Coding only; it does not imply Python Basics or Java Basics approval. New applications have separate three-attempt limits per topic; approved/pending duplicates are rejected. Past self-described fields can be retained or removed; new additions must be approved catalog topics.

The November 4 follow-up migration retires that temporary import category and assigns its topics to real subjects (Coding → Programming), retaining topic IDs and qualification names. Approved subjects and topics appear dynamically in the Skill Tree. See `docs/skill-activities-rollout.md` for the later Overview changes and selective recovery details.

Matching continues to rank exact normalized topic matches first, with availability and learner ratings as tie breakers. Degrees do not restrict matches. Request payload field names remain compatible; selectors now show subject → topic labels with only each participant's approved qualifications.

## Reverting

Stop writes and preserve a fresh backup before undoing anything. Restore code from the pre-change source archive (or revert only the catalog commits after they are created), leaving the earlier local messaging work intact. Rebuild assets. The new table can safely remain while the old application runs; keeping it preserves suggestions and review history. Roll back the catalog migration only if deliberately discarding that catalog data, after backup. Profile strings and qualification records are not removed by the catalog rollback. Importing an old full SQL backup would replace newer activity and is not the default rollback method.
