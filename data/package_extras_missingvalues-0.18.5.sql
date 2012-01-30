# Table alterations
ALTER TABLE package_extras ADD qa_approval int(1) NOT NULL default 0;
ALTER TABLE package_extras ADD unit_tested int(1) NOT NULL default 0;