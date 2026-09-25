#
# PowerPHPBoard – Testdaten für den Docker-Entwicklungsstack
#
# NUR FÜR DIE LOKALE ENTWICKLUNG. Niemals auf einem Live-Server einspielen!
#
# docker-compose.yml lädt diese Datei nach install.sql, aber nur beim ersten
# Start mit leerem Datenbank-Volume (docker compose down -v setzt zurück).
#
# Testkonten (Anmeldung mit E-Mail-Adresse und Passwort):
#
#   RalphAdmin  Administrator  ralphadmin@example.com  Test1234!
#   RalphUser   Normal user    ralphuser@example.com   Test1234!
#
# Die Passwörter liegen als Argon2id-Hash vor (Security::hashPassword()).
#

UPDATE ppb_config
SET boardurl = 'http://localhost:8085',
    adminemail = 'admin@powerphpboard.local'
WHERE id = 1;

INSERT INTO ppb_users (username, email, password, homepage, icq, biography, signature, hideemail, logincookie, status, registered, lastvisit) VALUES
('RalphAdmin', 'ralphadmin@example.com', '$argon2id$v=19$m=65536,t=4,p=1$RGxBbUJkem0vZ2oxYzFNeg$NOapv9UW3O9fC3hlQFSy+0mgTuzvGYvw+rAmPJGdmiM', '', '', '', '', 'YES', 'YES', 'Administrator', UNIX_TIMESTAMP(), 0),
('RalphUser', 'ralphuser@example.com', '$argon2id$v=19$m=65536,t=4,p=1$S2tMeVhuWTJURWdaTjRURw$4SWi3NtjRCTx3Z3SdCncAX5ny8XQ3kZPLiNC5ya9DEg', '', '', '', '', 'YES', 'YES', 'Normal user', UNIX_TIMESTAMP(), 0);
