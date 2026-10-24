# Moodle Marketplace listing

Text and answers for marketplace.moodle.com → Submit a plugin → **Free**. Not part of the plugin zip.

## Basics

- **Plugin name:** Hyperstack Credentials
- **Frankenstyle:** `local_hyperstack`
- **Type:** Local plugin
- **Zip:** `local_hyperstack_1.0.0.zip` (one folder `hyperstack/`), built with `git archive --format=zip --prefix=hyperstack/ -o local_hyperstack_1.0.0.zip v1.0.0`
- **Moodle versions:** 4.5, 5.0, 5.1, 5.2, 5.3
- **Source:** https://github.com/hyperstack/moodle-hyperstack
- **Issues:** https://github.com/hyperstack/moodle-hyperstack/issues
- **Documentation:** https://docs.thehyperstack.com/integrations/moodle-plugin
- **Website:** https://thehyperstack.com
- **Licence:** GNU GPL v3 or later

## Short description

Issue Hyperstack certificates and badges automatically when learners complete a course.

## Description

Hyperstack Credentials connects your Moodle site to Hyperstack, a platform for digital certificates and Open Badges. Teachers choose one or more Hyperstack credential groups for a course. When a learner completes the course, Hyperstack issues the credential and emails it to the learner, who can share it on LinkedIn and verify it online.

**Features**

- Connect the site once with a Hyperstack API key, stored encrypted. Test the connection from the settings page.
- In each course, teachers choose the credential groups under More → Hyperstack credentials.
- Credentials are issued on course completion, in a background task. Moodle's pages are never slowed down.
- Issue to learners who completed the course before the plugin was set up, with one click.
- A course report shows who received a credential, with a link to it, and why a credential was not sent, with Retry.
- No answer from Hyperstack or no credits left: Moodle tries again later by itself.
- A learner gets a credential from the same course and group only once.
- Course backup, copy and restore keep the course's credential groups.
- Privacy API: export and delete of the plugin's records.

**Requirements**

- **A Hyperstack account is required.** Sign up free at https://thehyperstack.com. Each credential uses Hyperstack credits.
- Moodle cron must run: course completion is calculated by a scheduled task.
- Course completion tracking and completion conditions set in the course.

**Privacy**

To issue a credential, the plugin sends the learner's full name and email address to Hyperstack. See https://thehyperstack.com/privacy-policy.

## Screenshots

From `docs/screenshots/`, in this order:

1. `4-course-groups.png`: choosing a course's credential groups
2. `5-report.png`: the course report
3. `1-settings.png`: plugin settings
4. `2-test-connection.png`: the connection test
5. `3-course-menu.png`: More → Hyperstack credentials

## For the reviewers

The plugin needs a Hyperstack account. Demo account for review:

- API key: *(create in the demo organization: Settings → Developers)*
- The demo organization has two credential groups and credits.

To test:

1. Install the plugin, go to Site administration → Plugins → Local plugins → Hyperstack Credentials, paste the API key, save, and click "test the connection".
2. Create a course with completion tracking on and the "Manual self completion" condition. Enrol a teacher and a student.
3. As the teacher, open More → Hyperstack credentials, select a group, save.
4. As the student, mark the course complete (Self completion block). Run cron.
5. As the teacher, open "See who received a credential": the student shows as Issued with a link.

Tests: PHPUnit (32 tests) and Behat (3 scenarios) pass on Moodle 4.5 and 5.3, PostgreSQL and MariaDB (`.github/workflows/ci.yml`).
