-- Project forms moved to Meta-gen, which has its own table and its own
-- component. A site that ran an earlier Exten-gen still carries this one;
-- export anything in it from there before updating, because this drops it.
DROP TABLE IF EXISTS `#__extengen_projectforms`;
