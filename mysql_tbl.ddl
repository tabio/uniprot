
CREATE TABLE `t_acc_phospho` (
  `uni_acc_no` char(32) NOT NULL,
  `position` int(4) NOT NULL,
  `residue_type` int(1) NOT NULL COMMENT '0:Ser, 1:Thr, 2:Tyr',
  `reference` varchar(256) DEFAULT NULL,
  `status` int(1) DEFAULT '0' COMMENT '0:none, 1:probable, 2:pubmed ids, 3:reference number',
  `reg_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`uni_acc_no`,`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8


CREATE TABLE `t_acc_snip` (
  `uni_acc_no` char(32) NOT NULL,
  `position` int(4) NOT NULL,
  `original` char(4) NOT NULL,
  `variation` char(4) NOT NULL,
  `reference` varchar(256) DEFAULT NULL,
  `status` int(1) DEFAULT '0' COMMENT '0:none, 1:probable, 2:pubmed ids, 3:reference number',
  `reg_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`uni_acc_no`,`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8


CREATE TABLE `t_chg_acc` (
  `gene_acc_no` char(32) NOT NULL,
  `uni_acc_no` char(32) NOT NULL,
  `sequence` text NOT NULL,
  `reg_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`gene_acc_no`,`uni_acc_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
