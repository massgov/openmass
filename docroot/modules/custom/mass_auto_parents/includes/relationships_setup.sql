# Create table indicators
  # ------------------------------------------------------------

DROP TABLE IF EXISTS `indicators`;

CREATE TABLE `indicators` (
  `parent_nid` int(11) unsigned NOT NULL,
  `child_nid` int(11) NOT NULL,
  `source_field` varchar(255) DEFAULT NULL,
  `parent_type` varchar(255) DEFAULT NULL,
  `child_type` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`parent_nid`,`child_nid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


# Dump of table relationships
# ------------------------------------------------------------

DROP TABLE IF EXISTS `relationships`;

CREATE TABLE `relationships` (
 `parent_nid` int unsigned DEFAULT NULL,
 `child_nid` int NOT NULL,
 `source_field` varchar(255) DEFAULT NULL,
 `parent_type` varchar(255) DEFAULT NULL,
 `child_type` varchar(255) DEFAULT NULL,
 `label` varchar(255) DEFAULT NULL,
 UNIQUE KEY `child` (`child_nid`),
 UNIQUE KEY `parent_nid` (`parent_nid`,`child_nid`),
 CONSTRAINT `parent_cannot_be_equal_to_child_CHK` CHECK ((`parent_nid` <> `child_nid`))
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `relationships` (`parent_nid`, `child_nid`, `source_field`, `parent_type`, `child_type`)
VALUES
  (14636,14506,'topics','topic_page','topic_page'),
  (14636,32736,'topics','topic_page','topic_page'),
  (14636,53196,'topics','topic_page','topic_page'),
  (14636,57366,'topics','topic_page','topic_page'),
  (14636,64061,'topics','topic_page','topic_page'),
  (14636,98121,'topics','topic_page','topic_page'),
  (14636,272411,'topics','topic_page','topic_page'),
  (14636,471431,'topics','topic_page','topic_page'),
  (16056,16016,'topics','topic_page','topic_page'),
  (16056,16171,'topics','topic_page','topic_page'),
  (16056,16741,'topics','topic_page','topic_page'),
  (16056,398471,'topics','topic_page','topic_page'),
  (16056,430986,'topics','topic_page','topic_page'),
  (16056,439631,'topics','topic_page','topic_page'),
  (16286,16301,'topics','topic_page','topic_page'),
  (16301,218276,'topics','topic_page','topic_page'),
  (16431,30721,'topics','topic_page','topic_page'),
  (16466,16416,'topics','topic_page','topic_page'),
  (16466,16441,'topics','topic_page','topic_page'),
  (16466,16446,'topics','topic_page','topic_page'),
  (16466,23756,'topics','topic_page','topic_page'),
  (16466,478726,'topics','topic_page','topic_page'),
  (16506,16281,'topics','topic_page','topic_page'),
  (16506,16921,'topics','topic_page','topic_page'),
  (16506,450631,'topics','topic_page','topic_page'),
  (16596,444451,'topics','topic_page','topic_page'),
  (16741,60741,'topics','topic_page','topic_page'),
  (16741,112856,'topics','topic_page','topic_page'),
  (16741,426326,'topics','topic_page','topic_page'),
  (16751,16586,'topics','topic_page','topic_page'),
  (16751,16596,'topics','topic_page','topic_page'),
  (16751,16631,'topics','topic_page','topic_page'),
  (16751,424616,'topics','topic_page','topic_page'),
  (16751,424621,'topics','topic_page','topic_page'),
  (16751,432061,'topics','topic_page','topic_page'),
  (16956,17091,'topics','topic_page','topic_page'),
  (16956,17191,'topics','topic_page','topic_page'),
  (16956,17491,'topics','topic_page','topic_page'),
  (16956,90321,'topics','topic_page','topic_page'),
  (16956,395851,'topics','topic_page','topic_page'),
  (17021,402571,'topics','topic_page','topic_page'),
  (17021,438686,'topics','topic_page','topic_page'),
  (17036,329171,'topics','topic_page','topic_page'),
  (17036,418286,'topics','topic_page','topic_page'),
  (17036,487286,'topics','topic_page','topic_page'),
  (17091,21616,'topics','topic_page','topic_page'),
  (17091,105821,'topics','topic_page','topic_page'),
  (17091,114371,'topics','topic_page','topic_page'),
  (17091,328666,'topics','topic_page','topic_page'),
  (17091,380711,'topics','topic_page','topic_page'),
  (17091,380721,'topics','topic_page','topic_page'),
  (17091,482631,'topics','topic_page','topic_page'),
  (17186,310796,'topics','topic_page','topic_page'),
  (17211,16651,'topics','topic_page','topic_page'),
  (17211,16691,'topics','topic_page','topic_page'),
  (17211,16706,'topics','topic_page','topic_page'),
  (17211,165426,'topics','topic_page','topic_page'),
  (17216,62841,'topics','topic_page','topic_page'),
  (17216,62856,'topics','topic_page','topic_page'),
  (17216,62866,'topics','topic_page','topic_page'),
  (17216,62876,'topics','topic_page','topic_page'),
  (17216,62886,'topics','topic_page','topic_page'),
  (17216,343076,'topics','topic_page','topic_page'),
  (17246,165226,'topics','topic_page','topic_page'),
  (17246,165241,'topics','topic_page','topic_page'),
  (17246,165246,'topics','topic_page','topic_page'),
  (17246,165261,'topics','topic_page','topic_page'),
  (17246,202016,'topics','topic_page','topic_page'),
  (17246,204201,'topics','topic_page','topic_page'),
  (17246,242736,'topics','topic_page','topic_page'),
  (17246,253036,'topics','topic_page','topic_page'),
  (17246,343586,'topics','topic_page','topic_page'),
  (17256,16836,'topics','topic_page','topic_page'),
  (17256,16846,'topics','topic_page','topic_page'),
  (17256,16856,'topics','topic_page','topic_page'),
  (17256,16871,'topics','topic_page','topic_page'),
  (17261,414266,'topics','topic_page','topic_page'),
  (17276,16906,'topics','topic_page','topic_page'),
  (17281,156726,'topics','topic_page','topic_page'),
  (17281,457796,'topics','topic_page','topic_page'),
  (17281,457981,'topics','topic_page','topic_page'),
  (17281,470796,'topics','topic_page','topic_page'),
  (17286,16941,'topics','topic_page','topic_page'),
  (17286,16946,'topics','topic_page','topic_page'),
  (17296,16711,'topics','topic_page','topic_page'),
  (17296,16716,'topics','topic_page','topic_page'),
  (17296,16831,'topics','topic_page','topic_page'),
  (17301,430541,'topics','topic_page','topic_page'),
  (17316,16431,'topics','topic_page','topic_page'),
  (17361,19021,'topics','topic_page','topic_page'),
  (17361,458801,'topics','topic_page','topic_page'),
  (17361,458811,'topics','topic_page','topic_page'),
  (17361,585526,'topics','topic_page','topic_page'),
  (17381,109766,'topics','topic_page','topic_page'),
  (17406,189836,'topics','topic_page','topic_page'),
  (17406,196556,'topics','topic_page','topic_page'),
  (17406,201456,'topics','topic_page','topic_page'),
  (17406,250146,'topics','topic_page','topic_page'),
  (17406,278216,'topics','topic_page','topic_page'),
  (17406,333846,'topics','topic_page','topic_page'),
  (17406,435901,'topics','topic_page','topic_page'),
  (17421,17436,'topics','topic_page','topic_page'),
  (17421,201821,'topics','topic_page','topic_page'),
  (17421,201901,'topics','topic_page','topic_page'),
  (17451,19661,'topics','topic_page','topic_page'),
  (17491,169631,'topics','topic_page','topic_page'),
  (17496,53456,'topics','topic_page','topic_page'),
  (17496,120976,'topics','topic_page','topic_page'),
  (17496,478931,'topics','topic_page','topic_page'),
  (17566,17021,'topics','topic_page','topic_page'),
  (17566,17026,'topics','topic_page','topic_page'),
  (17566,17036,'topics','topic_page','topic_page'),
  (17566,17046,'topics','topic_page','topic_page'),
  (17566,17056,'topics','topic_page','topic_page'),
  (17566,17076,'topics','topic_page','topic_page'),
  (17566,17086,'topics','topic_page','topic_page'),
  (17566,17301,'topics','topic_page','topic_page'),
  (17566,17351,'topics','topic_page','topic_page'),
  (17566,17361,'topics','topic_page','topic_page'),
  (17566,18581,'topics','topic_page','topic_page'),
  (17566,127581,'topics','topic_page','topic_page'),
  (17566,338681,'topics','topic_page','topic_page'),
  (17566,377696,'topics','topic_page','topic_page'),
  (17566,386786,'topics','topic_page','topic_page'),
  (17566,444421,'topics','topic_page','topic_page'),
  (17566,452376,'topics','topic_page','topic_page'),
  (17566,458881,'topics','topic_page','topic_page'),
  (17571,17376,'topics','topic_page','topic_page'),
  (17571,17381,'topics','topic_page','topic_page'),
  (17571,19371,'topics','topic_page','topic_page'),
  (17571,319831,'topics','topic_page','topic_page'),
  (17571,452341,'topics','topic_page','topic_page'),
  (17576,17096,'topics','topic_page','topic_page'),
  (17576,17106,'topics','topic_page','topic_page'),
  (17576,17406,'topics','topic_page','topic_page'),
  (17576,17421,'topics','topic_page','topic_page'),
  (17576,17596,'topics','topic_page','topic_page'),
  (17576,23461,'topics','topic_page','topic_page'),
  (17576,206896,'topics','topic_page','topic_page'),
  (17576,253471,'topics','topic_page','topic_page'),
  (17576,257076,'topics','topic_page','topic_page'),
  (17576,257861,'topics','topic_page','topic_page'),
  (17596,35566,'topics','topic_page','topic_page'),
  (17601,17151,'topics','topic_page','topic_page'),
  (17601,17161,'topics','topic_page','topic_page'),
  (17601,17171,'topics','topic_page','topic_page'),
  (17601,17181,'topics','topic_page','topic_page'),
  (17601,17186,'topics','topic_page','topic_page'),
  (17601,71131,'topics','topic_page','topic_page'),
  (17601,183716,'topics','topic_page','topic_page'),
  (17601,431976,'topics','topic_page','topic_page'),
  (17631,17496,'topics','topic_page','topic_page'),
  (17631,17511,'topics','topic_page','topic_page'),
  (17631,17641,'topics','topic_page','topic_page'),
  (17631,23466,'topics','topic_page','topic_page'),
  (17631,104756,'topics','topic_page','topic_page'),
  (17631,344151,'topics','topic_page','topic_page'),
  (17631,346226,'topics','topic_page','topic_page'),
  (17651,17281,'topics','topic_page','topic_page'),
  (17651,17291,'topics','topic_page','topic_page'),
  (17651,17516,'topics','topic_page','topic_page'),
  (17651,17521,'topics','topic_page','topic_page'),
  (17651,17531,'topics','topic_page','topic_page'),
  (17651,19916,'topics','topic_page','topic_page'),
  (17656,100501,'topics','topic_page','topic_page'),
  (17656,102131,'topics','topic_page','topic_page'),
  (17656,136166,'topics','topic_page','topic_page'),
  (17656,156786,'topics','topic_page','topic_page'),
  (17656,213596,'topics','topic_page','topic_page'),
  (18581,134396,'topics','topic_page','topic_page'),
  (18581,155516,'topics','topic_page','topic_page'),
  (18581,348541,'topics','topic_page','topic_page'),
  (18581,348556,'topics','topic_page','topic_page'),
  (18581,348571,'topics','topic_page','topic_page'),
  (18581,584711,'topics','topic_page','topic_page'),
  (19711,17206,'topics','topic_page','topic_page'),
  (19711,17246,'topics','topic_page','topic_page'),
  (19711,169371,'topics','topic_page','topic_page'),
  (19711,315726,'topics','topic_page','topic_page'),
  (19711,380101,'topics','topic_page','topic_page'),
  (19916,17396,'topics','topic_page','topic_page'),
  (21166,16311,'topics','topic_page','topic_page'),
  (21166,16316,'topics','topic_page','topic_page'),
  (21166,16326,'topics','topic_page','topic_page'),
  (21166,16351,'topics','topic_page','topic_page'),
  (21186,16286,'topics','topic_page','topic_page'),
  (21186,16291,'topics','topic_page','topic_page'),
  (21186,16506,'topics','topic_page','topic_page'),
  (21186,374956,'topics','topic_page','topic_page'),
  (21186,428891,'topics','topic_page','topic_page'),
  (22431,17116,'topics','topic_page','topic_page'),
  (22431,17216,'topics','topic_page','topic_page'),
  (22431,17451,'topics','topic_page','topic_page'),
  (22431,17566,'topics','topic_page','topic_page'),
  (22431,17571,'topics','topic_page','topic_page'),
  (22431,17576,'topics','topic_page','topic_page'),
  (22431,17601,'topics','topic_page','topic_page'),
  (22431,17631,'topics','topic_page','topic_page'),
  (22431,17651,'topics','topic_page','topic_page'),
  (22431,17656,'topics','topic_page','topic_page'),
  (22431,19711,'topics','topic_page','topic_page'),
  (22431,30511,'topics','topic_page','topic_page'),
  (22446,16466,'topics','topic_page','topic_page'),
  (22446,16751,'topics','topic_page','topic_page'),
  (22446,16956,'topics','topic_page','topic_page'),
  (22446,17061,'topics','topic_page','topic_page'),
  (22446,17316,'topics','topic_page','topic_page'),
  (22446,21166,'topics','topic_page','topic_page'),
  (22446,21186,'topics','topic_page','topic_page'),
  (22536,16056,'topics','topic_page','topic_page'),
  (22536,16121,'topics','topic_page','topic_page'),
  (22536,16136,'topics','topic_page','topic_page'),
  (22536,16146,'topics','topic_page','topic_page'),
  (22536,16156,'topics','topic_page','topic_page'),
  (22536,17261,'topics','topic_page','topic_page'),
  (22551,17211,'topics','topic_page','topic_page'),
  (22551,17256,'topics','topic_page','topic_page'),
  (22551,17276,'topics','topic_page','topic_page'),
  (22551,17286,'topics','topic_page','topic_page'),
  (22551,17296,'topics','topic_page','topic_page'),
  (22551,507221,'topics','topic_page','topic_page'),
  (23461,183706,'topics','topic_page','topic_page'),
  (23461,201466,'topics','topic_page','topic_page'),
  (32736,14331,'topics','topic_page','topic_page'),
  (32736,14491,'topics','topic_page','topic_page'),
  (32736,14521,'topics','topic_page','topic_page'),
  (32736,14526,'topics','topic_page','topic_page'),
  (32736,14581,'topics','topic_page','topic_page'),
  (32736,14586,'topics','topic_page','topic_page'),
  (32736,14591,'topics','topic_page','topic_page'),
  (32736,14611,'topics','topic_page','topic_page'),
  (32736,55011,'topics','topic_page','topic_page'),
  (32736,168606,'topics','topic_page','topic_page'),
  (32736,168631,'topics','topic_page','topic_page'),
  (53711,92386,'topics','topic_page','topic_page'),
  (57366,57426,'topics','topic_page','topic_page'),
  (57366,58036,'topics','topic_page','topic_page'),
  (57366,58041,'topics','topic_page','topic_page'),
  (57366,58051,'topics','topic_page','topic_page'),
  (57366,58071,'topics','topic_page','topic_page'),
  (57366,58081,'topics','topic_page','topic_page'),
  (57366,58086,'topics','topic_page','topic_page'),
  (57366,58096,'topics','topic_page','topic_page'),
  (57366,58101,'topics','topic_page','topic_page'),
  (57366,58536,'topics','topic_page','topic_page'),
  (57366,58556,'topics','topic_page','topic_page'),
  (57366,58566,'topics','topic_page','topic_page'),
  (57366,58586,'topics','topic_page','topic_page'),
  (57366,58596,'topics','topic_page','topic_page'),
  (57366,58616,'topics','topic_page','topic_page'),
  (57366,58626,'topics','topic_page','topic_page'),
  (57366,58641,'topics','topic_page','topic_page'),
  (57366,58661,'topics','topic_page','topic_page'),
  (57366,58736,'topics','topic_page','topic_page'),
  (57366,58756,'topics','topic_page','topic_page'),
  (57366,58766,'topics','topic_page','topic_page'),
  (57366,74636,'topics','topic_page','topic_page'),
  (60741,53711,'topics','topic_page','topic_page'),
  (60741,53881,'topics','topic_page','topic_page'),
  (62856,218676,'topics','topic_page','topic_page'),
  (72206,71166,'topics','topic_page','topic_page'),
  (71166,71196,'topics','topic_page','topic_page'),
  (71166,71231,'topics','topic_page','topic_page'),
  (71166,111791,'topics','topic_page','topic_page'),
  (22446,72206,'topics','topic_page','topic_page'),
  (72206,71186,'topics','topic_page','topic_page'),
  (165226,301796,'topics','topic_page','topic_page'),
  (165261,476706,'topics','topic_page','topic_page'),
  (196556,196481,'topics','topic_page','topic_page'),
  (196556,196531,'topics','topic_page','topic_page'),
  (250146,298286,'topics','topic_page','topic_page'),
  (253471,183981,'topics','topic_page','topic_page'),
  (253471,201851,'topics','topic_page','topic_page'),
  (253471,204741,'topics','topic_page','topic_page'),
  (253471,211171,'topics','topic_page','topic_page'),
  (257076,201386,'topics','topic_page','topic_page'),
  (257076,257051,'topics','topic_page','topic_page'),
  (272411,74576,'topics','topic_page','topic_page'),
  (310796,347756,'topics','topic_page','topic_page'),
  (319831,102186,'topics','topic_page','topic_page'),
  (344151,404861,'topics','topic_page','topic_page'),
  (344151,407286,'topics','topic_page','topic_page'),
  (344151,407296,'topics','topic_page','topic_page'),
  (344151,407306,'topics','topic_page','topic_page'),
  (374956,374606,'topics','topic_page','topic_page'),
  (374956,374626,'topics','topic_page','topic_page'),
  (386786,431006,'topics','topic_page','topic_page'),
  (402571,403381,'topics','topic_page','topic_page'),
  (430541,430401,'topics','topic_page','topic_page'),
  (471431,404931,'topics','topic_page','topic_page'),
  (null,14636,'topics',null,'topic_page'),
  (null,22431,'topics',null,'topic_page'),
  (null,22446,'topics',null,'topic_page'),
  (null,22536,'topics',null,'topic_page'),
  (null,22551,'topics',null,'topic_page'),
  (585526,458866,'topics','topic_page','topic_page');


INSERT IGNORE INTO indicators (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  fld.entity_id as parent_nid,
  fld.field_service_ref_guide_page_1_target_id AS child_nid,
  'field_service_ref_guide_page_1' as source_field,
  nfd_parent.type as parent_type,
  nfd_child.type as child_type
FROM
  node__field_service_ref_guide_page_1 fld,
  node_field_data nfd_parent,
  node_field_data nfd_child
WHERE
    fld.entity_id = nfd_parent.nid AND
    fld.field_service_ref_guide_page_1_target_id = nfd_child.nid AND
    nfd_parent.status = 1 AND
    nfd_child.type <> 'topic_page' AND
    nfd_child.status = 1;
# ------------------------------------------------------------
INSERT IGNORE INTO indicators (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  fld.entity_id as parent_nid,
  fld.field_service_eligibility_info_target_id AS child_nid,
  'field_service_eligibility_info' as source_field,
  nfd_parent.type as parent_type,
  nfd_child.type as child_type
FROM
  node__field_service_eligibility_info fld,
  node_field_data nfd_parent,
  node_field_data nfd_child
WHERE
    fld.entity_id = nfd_parent.nid AND
    fld.field_service_eligibility_info_target_id = nfd_child.nid AND
    nfd_parent.status = 1 AND
    nfd_child.type <> 'topic_page' AND
    nfd_child.status = 1;
# ------------------------------------------------------------
INSERT IGNORE INTO indicators (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  fld.entity_id as parent_nid,
  fld.field_service_ref_locations_target_id AS child_nid,
  'field_service_ref_locations' as source_field,
  nfd_parent.type as parent_type,
  nfd_child.type as child_type
FROM
  node__field_service_ref_locations fld,
  node_field_data nfd_parent,
  node_field_data nfd_child
WHERE
    fld.entity_id = nfd_parent.nid AND
    fld.field_service_ref_locations_target_id = nfd_child.nid AND
    nfd_parent.status = 1 AND
    nfd_child.type <> 'topic_page' AND
    nfd_child.status = 1;
# ------------------------------------------------------------
INSERT IGNORE INTO indicators (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  fld.field_service_ref_services_6_target_id as parent_nid,
  fld.entity_id AS child_nid,
  'field_service_ref_services_6' as source_field,
  nfd_parent.type as parent_type,
  nfd_child.type as child_type
FROM
  node__field_service_ref_services_6 fld,
  node_field_data nfd_parent,
  node_field_data nfd_child
WHERE
    fld.entity_id = nfd_child.nid AND
    fld.field_service_ref_services_6_target_id = nfd_parent.nid AND
    nfd_parent.status = 1 AND
    nfd_child.type = 'decision_tree' AND
    nfd_child.status = 1;
# ------------------------------------------------------------
INSERT IGNORE INTO indicators (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  fld.field_event_ref_parents_target_id as parent_nid,
  fld.entity_id AS child_nid,
  'field_event_ref_parents' as source_field,
  nfd_parent.type as parent_type,
  nfd_child.type as child_type
FROM
  node__field_event_ref_parents fld,
  node_field_data nfd_parent,
  node_field_data nfd_child
WHERE
    fld.entity_id = nfd_child.nid AND
    fld.field_event_ref_parents_target_id = nfd_parent.nid AND
    nfd_parent.status = 1 AND
    nfd_child.status = 1 AND
    nfd_parent.type <> 'org_page' AND
    nfd_child.type <> 'topic_page';
# ------------------------------------------------------------
INSERT IGNORE INTO indicators (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  entity_id as parent_nid,
  cast(substring(field_service_ref_actions_uri, 13) as UNSIGNED) as child_nid,
  'field_service_ref_actions_uri' as source_field,
  nfd_parent.type as parent_type,
  nfd_child.type as child_type
FROM
  node__field_service_ref_actions fld,
  node_field_data nfd_parent,
  node_field_data nfd_child
where
    fld.field_service_ref_actions_uri like 'entity:node%' and
    nfd_parent.nid = fld.entity_id and
    nfd_child.nid = substring(field_service_ref_actions_uri, 13) and
    nfd_parent.status = 1 and
    nfd_child.type <> 'topic_page' AND
    nfd_child.status = 1;
# ------------------------------------------------------------
INSERT IGNORE INTO indicators (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  entity_id as parent_nid,
  cast(substring(field_service_links_uri, 13) as UNSIGNED) as child_nid,
  'field_service_links' as source_field,
  nfd_parent.type as parent_type,
  nfd_child.type as child_type
FROM
  node__field_service_links fld,
  node_field_data nfd_parent,
  node_field_data nfd_child
where
    fld.field_service_links_uri like 'entity:node%' and
    nfd_parent.nid = fld.entity_id and
    nfd_child.nid = substring(field_service_links_uri, 13) and
    nfd_parent.status = 1 and
    nfd_child.type not in ('topic_page', 'org_page') AND
    nfd_child.status = 1;
# ------------------------------------------------------------
INSERT IGNORE INTO indicators (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  entity_id as parent_nid,
  cast(substring(field_service_ref_actions_2_uri, 13) as UNSIGNED) as child_nid,
  'field_service_ref_actions_2' as source_field,
  nfd_parent.type as parent_type,
  nfd_child.type as child_type
FROM
  node__field_service_ref_actions_2 fld,
  node_field_data nfd_parent,
  node_field_data nfd_child
where
    fld.field_service_ref_actions_2_uri like 'entity:node%' and
    nfd_parent.nid = fld.entity_id and
    nfd_child.nid = substring(field_service_ref_actions_2_uri, 13) and
    nfd_parent.status = 1 and
    nfd_child.type not in ('topic_page', 'org_page') AND
    nfd_child.status = 1;
# ------------------------------------------------------------
INSERT IGNORE INTO indicators (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  entity_id as parent_nid,
  cast(substring(field_service_key_info_links_6_uri, 13) as UNSIGNED) as child_nid,
  'field_service_key_info_links_6' as source_field,
  nfd_parent.type as parent_type,
  nfd_child.type as child_type
FROM
  node__field_service_key_info_links_6 fld,
  node_field_data nfd_parent,
  node_field_data nfd_child
where
    fld.field_service_key_info_links_6_uri like 'entity:node%' and
    nfd_parent.nid = fld.entity_id and
    nfd_child.nid = substring(field_service_key_info_links_6_uri, 13) and
    nfd_parent.status = 1 and
    nfd_child.type <> 'topic_page' AND
    nfd_child.status = 1;
# ------------------------------------------------------------
INSERT IGNORE INTO indicators (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  nfd_parent.nid as parent_nid,
  cast(substring(field_guide_section_links_4_uri, 13) as UNSIGNED) as child_nid,
  'field_guide_section_links_4' as source_field,
  nfd_parent.type as parent_type,
  nfd_child.type as child_type
FROM
  paragraph__field_guide_section_links_4 fld,
  node_field_data nfd_parent,
  node_field_data nfd_child,
  paragraphs_item_field_data pfd_parent
where
    fld.field_guide_section_links_4_uri like 'entity:node%' and
    pfd_parent.id = fld.entity_id and
    nfd_parent.nid = pfd_parent.parent_id and
    nfd_child.nid = substring(field_guide_section_links_4_uri, 13) and
    nfd_parent.status = 1 and
    nfd_child.status = 1 and
    nfd_child.type NOT IN ('org_page', 'topic_page', 'service_page');
# ------------------------------------------------------------
INSERT IGNORE INTO indicators (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  nfd_parent.nid as parent_nid,
  cast(substring(field_content_card_link_cards_uri, 13) as UNSIGNED) as child_nid,
  'field_content_card_link_cards' as source_field,
  nfd_parent.type as parent_type,
  nfd_child.type as child_type
FROM
  paragraph__field_content_card_link_cards fld,
  node_field_data nfd_parent,
  node_field_data nfd_child,
  paragraphs_item_field_data pfd_parent
where
    fld.field_content_card_link_cards_uri like 'entity:node%' and
    pfd_parent.id = fld.entity_id and
    nfd_parent.nid = pfd_parent.parent_id and
    nfd_child.nid = substring(field_content_card_link_cards_uri, 13) and
    nfd_parent.status = 1 and
    nfd_child.type not in ('topic_page', 'org_page') and
    nfd_child.status = 1;
# ------------------------------------------------------------
INSERT IGNORE INTO indicators (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  nfd_parent.nid as parent_nid,
  cast(substring(field_link_group_link_uri, 13) as UNSIGNED) as child_nid,
  'field_link_group_link' as source_field,
  nfd_parent.type as parent_type,
  nfd_child.type as child_type
FROM
  paragraph__field_link_group_link fld,
  node_field_data nfd_parent,
  node_field_data nfd_child,
  paragraphs_item_field_data pfd_parent,
  paragraphs_item_field_data pfd_parent_2
where
    fld.field_link_group_link_uri like 'entity:node%' and
    pfd_parent.id = fld.entity_id and
    pfd_parent_2.id = pfd_parent.parent_id and
    nfd_parent.nid = pfd_parent_2.parent_id and
    nfd_child.nid = substring(field_link_group_link_uri, 13) and
    nfd_parent.status = 1 and
    nfd_child.status = 1 and
    nfd_child.type not in ('org_page', 'topic_page');
# ------------------------------------------------------------
INSERT IGNORE INTO indicators (parent_nid, child_nid, source_field,
parent_type, child_type)
SELECT DISTINCT
  nfd_parent.nid as parent_nid,
  cast(substring(field_link_uri, 13) as UNSIGNED) as child_nid,
  'field_link' as source_field,
  nfd_parent.type as parent_type,
  nfd_child.type as child_type
FROM
  paragraph__field_link fld,
  node_field_data nfd_parent,
  node_field_data nfd_child,
  paragraphs_item_field_data pfd_parent,
  paragraphs_item_field_data pfd_parent_2
where
    fld.field_link_uri like 'entity:node%' and
    pfd_parent.id = fld.entity_id and
    pfd_parent_2.id = pfd_parent.parent_id and
    nfd_parent.nid = pfd_parent_2.parent_id and
    nfd_child.nid = substring(field_link_uri, 13) and
    nfd_parent.status = 1 and
    nfd_child.status = 1 and
    nfd_child.type not in ('org_page', 'topic_page');
# ------------------------------------------------------------
INSERT IGNORE INTO relationships (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT
  s.nid,
  r.child_nid,
  r.source_field,
  n_parent.type,
  n_child.type
FROM indicators r
       JOIN mass_superset_data s ON r.parent_nid = s.nid
       JOIN node_field_data n_parent ON s.nid = n_parent.nid
       JOIN node_field_data n_child ON r.child_nid = n_child.nid
       JOIN (
  SELECT
    child_nid,
    max(page_views) AS max_page_views
  FROM (
         SELECT
           r.child_nid AS child_nid,
           r.parent_nid AS parent_nid,
           d.pageviews AS page_views
         FROM
           indicators r,
           mass_superset_data d
         WHERE r.parent_nid = d.nid
         GROUP BY
           r.child_nid,
           r.parent_nid
         ORDER BY child_nid
       ) AS max_page_views
  GROUP BY child_nid
) p ON r.child_nid = p.child_nid
WHERE p.max_page_views = s.pageviews;
# ------------------------------------------------------------
INSERT IGNORE INTO relationships (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  nfd_parent.nid as parent_nid,
  cast(substring(field_listitemlink_item_uri, 13) as UNSIGNED) as child_nid,
  'manual_curated_list' as source_field,
  nfd_parent.type as parent_type,
  nfd_child.type as child_type
FROM
  paragraph__field_listitemlink_item fld,
  node_field_data nfd_parent,
  node_field_data nfd_child,
  paragraphs_item_field_data pfd_parent,
  paragraphs_item_field_data pfd_parent_2
where
    fld.field_listitemlink_item_uri like 'entity:node%' and
    pfd_parent.id = fld.entity_id and
    pfd_parent_2.id = pfd_parent.parent_id and
    nfd_parent.nid = pfd_parent_2.parent_id and
    nfd_child.nid = substring(field_listitemlink_item_uri, 13) and
    nfd_parent.status = 1 and
    nfd_child.status = 1 and
    nfd_child.type not in ('org_page', 'service_page') AND
    nfd_child.nid NOT IN ( SELECT child_nid FROM relationships )
ORDER BY child_nid, parent_nid;
# ------------------------------------------------------------
INSERT IGNORE INTO relationships (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  n_parent.nid,
  n_child.nid,
  'dynamic_curated_list',
  n_parent.type,
  n_child.type
FROM
  node_field_data n_parent,
  paragraphs_item_field_data pi,
  paragraph__field_listdynamic_label pf,
  node__field_reusable_label nf,
  node_field_data n_child
WHERE
    n_parent.nid = pi.parent_id AND
    pi.id = pf.entity_id AND
    pf.field_listdynamic_label_target_id = nf.field_reusable_label_target_id AND
    nf.entity_id = n_child.nid AND
    n_parent.status = 1 AND
    n_child.status = 1 AND
    n_child.nid NOT IN (select child_nid from relationships) AND
    n_child.type not in ('org_page', 'service_page')
ORDER BY n_child.nid, n_parent.nid;
# ------------------------------------------------------------
INSERT IGNORE INTO relationships (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  n_parent.nid,
  n_child.nid,
  'binder_page_group',
  n_parent.type,
  n_child.type
FROM
  paragraphs_item_field_data p,
  node_field_data n_parent,
  node_field_data n_child,
  paragraph__field_page_group_page fld
WHERE
    p.parent_id = n_parent.nid AND
    fld.entity_id = p.id AND
    substring(fld.field_page_group_page_uri, 13) = n_child.nid AND
    fld.field_page_group_page_uri LIKE 'entity:node%' AND
    n_child.status = 1 AND
    n_parent.status = 1 AND
    n_parent.type = 'binder' AND
    p.type = 'page_group'
ORDER BY n_child.nid, n_parent.nid;
# ------------------------------------------------------------
INSERT IGNORE INTO relationships (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  n_parent.nid,
  n_child.nid,
  'binder_page',
  n_parent.type,
  n_child.type
FROM
  paragraphs_item_field_data p,
  node_field_data n_parent,
  node_field_data n_child,
  paragraph__field_page_page fld
WHERE
    p.parent_id = n_parent.nid AND
    fld.entity_id = p.id AND
    substring(fld.field_page_page_uri, 13) = n_child.nid AND
    fld.field_page_page_uri LIKE 'entity:node%' AND
    n_child.status = 1 AND
    n_parent.status = 1 AND
    n_parent.type = 'binder' AND
    p.type = 'page'
ORDER BY n_child.nid, n_parent.nid;
# ------------------------------------------------------------
INSERT IGNORE INTO relationships (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  n_parent.nid AS parent_nid,
  n_child.nid AS child_nid,
  'organization_parent',
  n_parent.type,
  n_child.type
FROM
  node_field_data n_parent,
  node_field_data n_child,
  node__field_parent fld
WHERE
    n_child.nid = fld.entity_id AND
    fld.field_parent_target_id = n_parent.nid AND
    n_child.status = 1 AND
    n_parent.status = 1;
# ------------------------------------------------------------
INSERT IGNORE INTO relationships (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  n_parent.nid,
  n_child.nid,
  'decision_relationships',
  n_parent.type,
  n_child.type
FROM
  node_field_data n_child,
  node_field_data n_parent,
  descendant_relations dr
WHERE
    n_child.nid = dr.destination_id AND
    n_parent.nid = dr.source_id AND
    n_parent.status = 1 AND
    n_child.type = 'decision' AND
    n_child.status = 1 AND
    n_child.nid NOT IN (SELECT child_nid FROM relationships) AND
    n_parent.type <> 'org_page'
ORDER BY n_child.nid, n_parent.nid;
# ------------------------------------------------------------
INSERT IGNORE INTO relationships (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  o.field_organizations_target_id,
  n.nid,
  'field_organizations',
  'org_page',
  n.type
FROM
  node_field_data n,
  node__field_organizations o
WHERE
    n.nid NOT IN (
    SELECT child_nid FROM relationships
  ) AND
    n.status = 1 AND
    n.type in ('location', 'event', 'news', 'campaign_landing', 'person', 'advisory', 'regulation', 'executive_order') AND
    n.nid = o.entity_id AND
    o.delta = 0;
# ------------------------------------------------------------
INSERT IGNORE INTO relationships (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  null AS parent_nid,
  n.nid AS child_nid,
  'organization' as source_field,
  null as parent_type,
  n.type as child_type
FROM
  node_field_data n
WHERE
    n.status = 1 AND
    n.type = 'org_page' AND
    n.nid not in (select child_nid from relationships);
# ------------------------------------------------------------
INSERT INTO `relationships` (`parent_nid`, `child_nid`, `source_field`, `parent_type`, `child_type`)
VALUES
	(239401,214511,'rules_relationships','guide_page','rules'),
	(239401,214706,'rules_relationships','guide_page','rules'),
	(239401,214746,'rules_relationships','guide_page','rules'),
	(239401,214816,'rules_relationships','guide_page','rules'),
	(239401,215676,'rules_relationships','guide_page','rules'),
	(239401,216091,'rules_relationships','guide_page','rules'),
	(239401,216641,'rules_relationships','guide_page','rules'),
	(239401,216946,'rules_relationships','guide_page','rules'),
	(239401,217391,'rules_relationships','guide_page','rules'),
	(239401,217451,'rules_relationships','guide_page','rules'),
	(239401,217501,'rules_relationships','guide_page','rules'),
	(239401,218186,'rules_relationships','guide_page','rules'),
	(239401,218221,'rules_relationships','guide_page','rules'),
	(219171,219176,'rules_relationships','rules','rules'),
	(219171,219191,'rules_relationships','rules','rules'),
	(219171,219301,'rules_relationships','rules','rules'),
	(219171,219316,'rules_relationships','rules','rules'),
	(219171,219336,'rules_relationships','rules','rules'),
	(219171,219361,'rules_relationships','rules','rules'),
	(219171,219406,'rules_relationships','rules','rules'),
	(219171,219686,'rules_relationships','rules','rules'),
	(223261,223286,'rules_relationships','rules','rules'),
	(223261,223341,'rules_relationships','rules','rules'),
	(223261,223366,'rules_relationships','rules','rules'),
	(223261,223391,'rules_relationships','rules','rules'),
	(223261,223421,'rules_relationships','rules','rules'),
	(223261,223446,'rules_relationships','rules','rules'),
	(223261,223481,'rules_relationships','rules','rules'),
	(223261,223606,'rules_relationships','rules','rules'),
	(223261,224031,'rules_relationships','rules','rules'),
	(223261,224156,'rules_relationships','rules','rules'),
	(223261,224366,'rules_relationships','rules','rules'),
	(223261,224476,'rules_relationships','rules','rules'),
	(223261,224576,'rules_relationships','rules','rules'),
	(223261,225136,'rules_relationships','rules','rules'),
	(223261,225331,'rules_relationships','rules','rules'),
	(223261,225816,'rules_relationships','rules','rules'),
	(223261,225841,'rules_relationships','rules','rules'),
	(223261,225876,'rules_relationships','rules','rules'),
	(223261,225941,'rules_relationships','rules','rules'),
	(223261,226011,'rules_relationships','rules','rules'),
	(223261,226091,'rules_relationships','rules','rules'),
	(223261,226371,'rules_relationships','rules','rules'),
	(223261,226426,'rules_relationships','rules','rules'),
	(223261,226536,'rules_relationships','rules','rules'),
	(223261,226611,'rules_relationships','rules','rules'),
	(223261,226661,'rules_relationships','rules','rules'),
	(223261,226701,'rules_relationships','rules','rules'),
	(223261,226841,'rules_relationships','rules','rules'),
	(223261,226866,'rules_relationships','rules','rules'),
	(223261,226926,'rules_relationships','rules','rules'),
	(223261,226946,'rules_relationships','rules','rules'),
	(223261,226981,'rules_relationships','rules','rules'),
	(223261,227011,'rules_relationships','rules','rules'),
	(223261,227101,'rules_relationships','rules','rules'),
	(223261,227121,'rules_relationships','rules','rules'),
	(223261,227126,'rules_relationships','rules','rules'),
	(223261,227161,'rules_relationships','rules','rules'),
	(223261,227206,'rules_relationships','rules','rules'),
	(223261,227231,'rules_relationships','rules','rules'),
	(223261,227286,'rules_relationships','rules','rules'),
	(223261,227301,'rules_relationships','rules','rules'),
	(223261,227311,'rules_relationships','rules','rules'),
	(223261,227326,'rules_relationships','rules','rules'),
	(223261,227346,'rules_relationships','rules','rules'),
	(223261,227366,'rules_relationships','rules','rules'),
	(223261,227371,'rules_relationships','rules','rules'),
	(223261,227386,'rules_relationships','rules','rules'),
	(223261,227406,'rules_relationships','rules','rules'),
	(223261,227426,'rules_relationships','rules','rules'),
	(223261,227441,'rules_relationships','rules','rules'),
	(223261,227461,'rules_relationships','rules','rules'),
	(223261,227856,'rules_relationships','rules','rules'),
	(223261,227891,'rules_relationships','rules','rules'),
	(223261,227926,'rules_relationships','rules','rules'),
	(223261,228011,'rules_relationships','rules','rules'),
	(223261,228066,'rules_relationships','rules','rules'),
	(223261,228071,'rules_relationships','rules','rules'),
	(223261,228086,'rules_relationships','rules','rules'),
	(223261,228091,'rules_relationships','rules','rules'),
	(228126,228156,'rules_relationships','rules','rules'),
	(228126,228186,'rules_relationships','rules','rules'),
	(228126,228201,'rules_relationships','rules','rules'),
	(228126,228246,'rules_relationships','rules','rules'),
	(228126,228306,'rules_relationships','rules','rules'),
	(228126,228376,'rules_relationships','rules','rules'),
	(228126,228526,'rules_relationships','rules','rules'),
	(228126,229076,'rules_relationships','rules','rules'),
	(229141,229291,'rules_relationships','rules','rules'),
	(229306,230801,'rules_relationships','rules','rules'),
	(239401,321156,'rules_relationships','guide_page','rules'),
	(364911,364961,'rules_relationships','rules','rules'),
	(364911,365036,'rules_relationships','rules','rules'),
	(364911,365146,'rules_relationships','rules','rules'),
	(364911,365211,'rules_relationships','rules','rules'),
	(364911,365221,'rules_relationships','rules','rules'),
	(364911,365231,'rules_relationships','rules','rules'),
	(364911,365281,'rules_relationships','rules','rules'),
	(364911,365311,'rules_relationships','rules','rules'),
	(364911,365341,'rules_relationships','rules','rules'),
	(364911,365486,'rules_relationships','rules','rules'),
	(364911,365536,'rules_relationships','rules','rules'),
	(364911,365566,'rules_relationships','rules','rules'),
	(364911,365596,'rules_relationships','rules','rules'),
	(364911,365761,'rules_relationships','rules','rules'),
	(364911,365771,'rules_relationships','rules','rules'),
	(364911,365791,'rules_relationships','rules','rules'),
	(223261,366186,'rules_relationships','rules','rules'),
	(515026,514536,'rules_relationships','rules','rules'),
	(515086,514576,'rules_relationships','rules','rules'),
	(239401,517681,'rules_relationships','guide_page','rules');
# ------------------------------------------------------------
# -- Assign QA Content
  INSERT IGNORE INTO relationships (parent_nid, child_nid, source_field, parent_type, child_type)
SELECT DISTINCT
  6011 AS parent_nid,
  n.nid AS child_nid,
  'QA_content' as source_field,
  'org_page' as parent_type,
  n.type as child_type
FROM
  node_field_data n
WHERE
    n.status = 1 AND
    n.title like '%_QA%' AND
    n.nid not in (select child_nid from relationships);
# ------------------------------------------------------------
# -- Services which use field_service_ref_services_6 don't create a relationship indicator but could still be valuable. We place them low to give other things a chance first.
INSERT IGNORE INTO relationships (parent_nid, child_nid, source_field, parent_type, child_type,label)
SELECT DISTINCT
  fld.field_service_ref_services_6_target_id as parent_nid,
  fld.entity_id AS child_nid,
  'field_service_ref_services_6' as source_field,
  nfd_parent.type as parent_type,
  nfd_child.type as child_type,
  'review'
FROM
  node__field_service_ref_services_6 fld,
  node_field_data nfd_parent,
  node_field_data nfd_child
WHERE
    fld.entity_id = nfd_child.nid AND
    fld.field_service_ref_services_6_target_id = nfd_parent.nid AND
    nfd_parent.status = 1 AND
    nfd_child.type <> 'decision_tree' AND
    nfd_child.status = 1;
# ------------------------------------------------------------
# -- Manual updates to fix looping structures
update relationships set parent_nid = 17566	where child_nid = 487771;
update relationships set parent_nid = 17566	where child_nid = 235896;
update relationships set parent_nid = 51071	where child_nid = 126101;
update relationships set parent_nid = 17576	where child_nid = 77091;
update relationships set parent_nid = 5826	where child_nid = 41221;
update relationships set parent_nid = 34321	where child_nid = 332361;
update relationships set parent_nid = 27491	where child_nid = 235791;
update relationships set parent_nid = 16056	where child_nid = 36171;
update relationships set parent_nid = 5981 where child_nid = 34506;
update relationships set parent_nid = 5471 where child_nid = 30951;
update relationships set parent_nid = 9876 where child_nid = 403976;
update relationships set parent_nid = 299866 where child_nid = 299991;
update relationships set parent_nid = 23356	where child_nid = 489526;
update relationships set parent_nid = 17036	where child_nid = 401426;
update relationships set parent_nid = 5836	where child_nid = 32351;
update relationships set parent_nid = 13661	where child_nid = 161686;
update relationships set parent_nid = 172121 where child_nid = 445411;
update relationships set parent_nid = 63511	where child_nid = 119161;
update relationships set parent_nid = 114636 where child_nid = 155676;
update relationships set parent_nid = 144466 where child_nid = 114636;
update relationships set parent_nid = 17036	where child_nid = 375811;
update relationships set parent_nid = 32161	where child_nid = 373771;
update relationships set parent_nid = 16466	where child_nid = 378156;
update relationships set parent_nid = 6661 where child_nid = 224736;
update relationships set parent_nid = 5456 where child_nid = 305071;
update relationships set parent_nid = 13651 where child_nid = 110026;
