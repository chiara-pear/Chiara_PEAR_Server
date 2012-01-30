# Database : pearserver
# --------------------------------------------------------

#
# Table structure for table `categories`
#

CREATE TABLE categories (
  id int(6) NOT NULL default '0',
  channel varchar(255) NOT NULL default '0',
  name varchar(255) NOT NULL default '',
  description text NOT NULL,
  alias varchar(50) default NULL,
  PRIMARY KEY  (id)
);
# --------------------------------------------------------

#
# Table structure for table `categories_seq`
#

CREATE TABLE categories_seq (
  id int(10) unsigned NOT NULL auto_increment,
  PRIMARY KEY  (id)
);
# --------------------------------------------------------

#
# Table structure for table `channels`
#

CREATE TABLE channels (
  channel varchar(100) NOT NULL default '',
  summary varchar(255) NOT NULL default '',
  alias varchar(100) NOT NULL default '',
  validatepackage varchar(255) default NULL,
  validatepackageversion varchar(25) default NULL,
  PRIMARY KEY  (channel)
);
# --------------------------------------------------------

#
# Table structure for table `handles`
#

CREATE TABLE handles (
  handle varchar(20) NOT NULL default '',
  name varchar(255) NOT NULL default '',
  email varchar(255) NOT NULL default '',
  uri varchar(255) NOT NULL default '',
  password varchar(50) NOT NULL default '',
  admin int(11) NOT NULL default '0',
  PRIMARY KEY  (handle)
);
# --------------------------------------------------------

#
# Table structure for table `maintainers`
#

CREATE TABLE maintainers (
  handle varchar(20) NOT NULL default '',
  channel varchar(25) NOT NULL default '',
  package varchar(80) NOT NULL default '',
  role varchar(30) NOT NULL default 'lead',
  active tinyint(4) NOT NULL default '1',
  PRIMARY KEY  (handle,channel,package)
);
# --------------------------------------------------------

#
# Table structure for table `package_extras`
#

CREATE TABLE package_extras (
  channel varchar(255) NOT NULL default '',
  package varchar(80) default NULL,
  cvs_uri varchar(255) NOT NULL default '',
  bugs_uri varchar(255) NOT NULL default '',
  docs_uri varchar(255) NOT NULL default ''
);
# --------------------------------------------------------

#
# Table structure for table `packages`
#

CREATE TABLE packages (
  channel varchar(255) NOT NULL default '',
  category_id int(6) NOT NULL default '0',
  package varchar(80) NOT NULL default '',
  license varchar(20) NOT NULL default '',
  licenseuri varchar(150) NOT NULL default '',
  summary text NOT NULL,
  description text NOT NULL,
  parent varchar(80) default NULL,
  deprecated_package varchar(80) NOT NULL default '',
  deprecated_channel varchar(255) NOT NULL default '',
  PRIMARY KEY  (channel,package)
);
# --------------------------------------------------------

#
# Table structure for table `releases`
#

CREATE TABLE releases (
  id int(11) NOT NULL default '0',
  channel varchar(25) NOT NULL default '',
  package varchar(80) NOT NULL default '',
  version varchar(20) NOT NULL default '',
  state enum('stable','beta','alpha','devel','snapshot') NOT NULL default 'stable',
  maintainer varchar(20) NOT NULL default '',
  license varchar(20) NOT NULL default '',
  summary text NOT NULL,
  description text NOT NULL,
  releasedate datetime NOT NULL default '0000-00-00 00:00:00',
  releasenotes text NOT NULL,
  filepath text NOT NULL,
  packagexml longtext NOT NULL,
  deps text NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY version (channel,package,version),
  KEY channel (channel,package)
);
# --------------------------------------------------------

#
# Table structure for table `releases_seq`
#

CREATE TABLE releases_seq (
  id int(10) unsigned NOT NULL auto_increment,
  PRIMARY KEY  (id)
);