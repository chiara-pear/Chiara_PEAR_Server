# Table alterations
ALTER TABLE package_extras CHANGE qa_approval qa_approval int(1) NOT NULL default 0;
ALTER TABLE package_extras CHANGE unit_tested unit_tested int(1) NOT NULL default 0;