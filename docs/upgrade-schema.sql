-- Bring an existing `donations` database up to the current schema.
--
-- Only needed for a database created before these changes. The migration
-- files were edited in place rather than added to, so `php spark migrate` has
-- nothing new to run and will leave the old columns exactly as they are —
-- which is why choosing "Living Related" or "Paired Exchange" on a screen
-- fails until this has been applied.
--
-- A database created from scratch (php spark migrate on an empty schema) is
-- already correct and must not be run through this.
--
--     mysql -u <user> -p donations < docs/upgrade-schema.sql
--
-- Take a backup first:  mysqldump -u <user> -p donations > donations-backup.sql

-- ---- recipients --------------------------------------------------------
-- Urgency was a four-level scale that only the waiting list read; it is one
-- yes/no question now. High and Critical carry over as urgent.
ALTER TABLE recipients ADD COLUMN is_urgent TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER dialysis_start;
UPDATE recipients SET is_urgent = 1 WHERE urgency IN ('high', 'critical');
ALTER TABLE recipients DROP INDEX urgency;
ALTER TABLE recipients DROP COLUMN urgency;
ALTER TABLE recipients ADD INDEX (is_urgent);

-- Diagnosis and Hospital came off the recipient screen.
ALTER TABLE recipients DROP COLUMN diagnosis;
ALTER TABLE recipients DROP COLUMN hospital;

-- The status list is now shared with `pairs`. `ready` had no screen behind it
-- and becomes `confirmed`; `cancelled` becomes `declined`.
UPDATE recipients SET status = 'active' WHERE status = 'ready';
UPDATE recipients SET status = 'closed' WHERE status = 'cancelled';
ALTER TABLE recipients MODIFY status
    ENUM('pending','confirmed','closed','completed','paired_exchange','on_hold','active','declined')
    NOT NULL DEFAULT 'pending';
UPDATE recipients SET status = 'confirmed' WHERE status = 'active' AND mrn IN (
    SELECT recipient_mrn FROM (SELECT recipient_mrn FROM pairs WHERE status = 'scheduled') AS p
);

-- ---- donors ------------------------------------------------------------
-- The donor screen never collected Hospital; the column was only ever filled
-- from the recipient form's box, which is gone.
ALTER TABLE donors DROP COLUMN hospital;

-- Living donors can now be recorded as related or unrelated to the recipient.
-- Existing `living` rows stay `living`: that part was never asked.
ALTER TABLE donors MODIFY donation_type
    ENUM('living','living_related','living_unrelated','deceased')
    NOT NULL DEFAULT 'living';

-- ---- pairs -------------------------------------------------------------
-- The same shared list. `scheduled` becomes `confirmed`, which is what it
-- meant: agreed but not yet done.
ALTER TABLE pairs MODIFY status
    ENUM('active','scheduled','on_hold','completed','closed','pending','confirmed','paired_exchange','declined')
    NOT NULL DEFAULT 'active';
UPDATE pairs SET status = 'confirmed' WHERE status = 'scheduled';
ALTER TABLE pairs MODIFY status
    ENUM('pending','confirmed','closed','completed','paired_exchange','on_hold','active','declined')
    NOT NULL DEFAULT 'pending';

-- A recipient in a pair carries that pair's status.
UPDATE recipients r
  JOIN pairs p ON p.recipient_mrn = r.mrn AND p.status <> 'closed'
   SET r.status = p.status;
