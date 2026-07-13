-- Add 'mill' type and clean up unused warehouse types
ALTER TABLE warehouses MODIFY type ENUM('wheat','mill','finished','general') DEFAULT 'general';
