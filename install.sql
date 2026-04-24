#
# Tabellenstruktur f�r Tabelle `ppb_boards`
#

CREATE TABLE ppb_boards (
  id int(11) NOT NULL auto_increment,
  title varchar(100) NOT NULL default '',
  description varchar(150) NOT NULL default '',
  type enum('Board','Boardcategory') NOT NULL default 'Board',
  mods varchar(250) NOT NULL default '',
  catid int(11) NOT NULL default '0',
  status enum('Open','Closed','Private') NOT NULL default 'Open',
  password varchar(255) NOT NULL default '',
  header varchar(250) NOT NULL default '',
  footer varchar(250) NOT NULL default '',
  bordercolor varchar(7) NOT NULL default '',
  tablebg1 varchar(7) NOT NULL default '',
  tablebg2 varchar(7) NOT NULL default '',
  tablebg3 varchar(7) NOT NULL default '',
  newthread varchar(250) NOT NULL default '',
  newpost varchar(250) NOT NULL default '',
  lastchange int(14) NOT NULL default '0',
  lastauthor int(11) NOT NULL default '0',
  PRIMARY KEY  (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
# --------------------------------------------------------

#
# Tabellenstruktur f�r Tabelle `ppb_config`
#

CREATE TABLE ppb_config (
  id int(11) NOT NULL auto_increment,
  boardtitle varchar(200) NOT NULL default '',
  boardurl varchar(250) NOT NULL default '',
  adminemail varchar(100) NOT NULL default '',
  header varchar(250) NOT NULL default '',
  footer varchar(250) NOT NULL default '',
  bordercolor varchar(7) NOT NULL default '',
  tablebg1 varchar(7) NOT NULL default '',
  tablebg2 varchar(7) NOT NULL default '',
  tablebg3 varchar(7) NOT NULL default '',
  htmlcode enum('ON','OFF') NOT NULL default 'ON',
  bbcode enum('ON','OFF') NOT NULL default 'ON',
  smilies enum('ON','OFF') NOT NULL default 'ON',
  newthread varchar(250) NOT NULL default '',
  newpost varchar(250) NOT NULL default '',
  language enum('English','Deutsch-Sie','Deutsch-Du') NOT NULL default 'English',
  PRIMARY KEY  (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
# --------------------------------------------------------

#
# Tabellenstruktur f�r Tabelle `ppb_posts`
#

CREATE TABLE ppb_posts (
  id int(11) NOT NULL auto_increment,
  boardid int(11) NOT NULL default '0',
  threadid int(11) NOT NULL default '0',
  type enum('Thread','Post') NOT NULL default 'Thread',
  status enum('Open','Closed') NOT NULL default 'Open',
  time int(14) NOT NULL default '0',
  author int(11) NOT NULL default '0',
  title varchar(150) NOT NULL default '',
  text text NOT NULL,
  icon varchar(100) NOT NULL default '',
  views int(11) NOT NULL default '0',
  ip varchar(100) NOT NULL default '',
  lastreply int(14) NOT NULL default '0',
  lastauthor int(11) NOT NULL default '0',
  PRIMARY KEY  (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
# --------------------------------------------------------

#
# Tabellenstruktur f�r Tabelle `ppb_users`
#

CREATE TABLE ppb_users (
  id int(11) NOT NULL auto_increment,
  username varchar(50) NOT NULL default '',
  email varchar(100) NOT NULL default '',
  password varchar(255) NOT NULL default '',
  homepage varchar(150) NOT NULL default '',
  icq varchar(20) NOT NULL default '',
  biography text NOT NULL,
  signature text NOT NULL,
  hideemail enum('YES','NO') NOT NULL default 'YES',
  logincookie enum('YES','NO') NOT NULL default 'YES',
  status enum('Deactivated','Normal user','Administrator') NOT NULL default 'Deactivated',
  registered int(14) NOT NULL default '0',
  lastvisit int(14) NOT NULL default '0',
  PRIMARY KEY  (id),
  UNIQUE KEY idx_users_username_unique (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
# --------------------------------------------------------

#
# Tabellenstruktur fuer Tabelle `ppb_visits`
#

CREATE TABLE ppb_visits (
  id int(11) NOT NULL auto_increment,
  userid int(11) NOT NULL default '0',
  vid int(11) NOT NULL default '0',
  time int(14) NOT NULL default '0',
  type enum('Board','Thread') NOT NULL default 'Board',
  password varchar(255) NOT NULL default '',
  PRIMARY KEY  (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
# --------------------------------------------------------

#
# Tabellenstruktur fuer Tabelle `ppb_password_resets`
#

CREATE TABLE ppb_password_resets (
  id int(11) NOT NULL auto_increment,
  userid int(11) NOT NULL,
  token_hash varchar(64) NOT NULL,
  expires_at int(14) NOT NULL,
  used_at int(14) NOT NULL default '0',
  created_at int(14) NOT NULL,
  PRIMARY KEY (id),
  INDEX idx_pwreset_userid (userid),
  INDEX idx_pwreset_token (token_hash),
  INDEX idx_pwreset_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
# --------------------------------------------------------

#
# Tabellenstruktur fuer Tabelle `ppb_rate_limits`
#

CREATE TABLE ppb_rate_limits (
  id int(11) NOT NULL auto_increment,
  action varchar(50) NOT NULL,
  identifier varchar(255) NOT NULL,
  attempts int(11) NOT NULL default '0',
  window_start int(14) NOT NULL,
  locked_until int(14) NOT NULL default '0',
  PRIMARY KEY (id),
  UNIQUE KEY idx_rl_action_identifier (action, identifier),
  INDEX idx_rl_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

#
# Administrator erstellen
#

INSERT INTO ppb_users (username, email, password, homepage, icq, biography, signature, hideemail, logincookie, status, registered, lastvisit) VALUES('Gott', 'gott@powerscripts.org', 'Z290dA==', 'http://www.powerscripts.org', '', 'Hab in sieben Tagen die Welt erschaffen ;)', 'MfG Gott', 'YES', 'YES', 'Administrator', '0', '0');

#
# Konfiguration erstellen
#

INSERT INTO ppb_config (boardtitle) VALUES('PowerPHPBoard 1.0 BETA');