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
# Table structure for table `package_extras`
#

CREATE TABLE package_extras (
  channel varchar(255) NOT NULL default '',
  package varchar(80) default NULL,
  cvs_uri varchar(255) NOT NULL default '',
  bugs_uri varchar(255) NOT NULL default '',
  docs_uri varchar(255) NOT NULL default ''
) TYPE=MyISAM;
# --------------------------------------------------------

# Table alterations

ALTER TABLE handles ADD uri VARCHAR( 255 );
ALTER TABLE maintainers CHANGE active active TINYINT( 4 ) DEFAULT '1' NOT NULL;
ALTER TABLE packages ADD category_id INT( 6 ) DEFAULT '0' NOT NULL ;
ALTER TABLE releases CHANGE packagexml packagexml LONGTEXT NOT NULL;