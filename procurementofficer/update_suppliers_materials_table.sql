-- Update suppliers_materials table structure to match materials table
-- Add new columns to suppliers_materials table

ALTER TABLE suppliers_materials 
ADD COLUMN category ENUM('Cement','Concrete','Steel','Bricks & Blocks','Wood & Timber','Tiles & Flooring','Paints & Coatings','Glass & Glazing','Plaster & Drywall','Roofing Sheets','Insulation Materials','Sealants & Waterproofing','Electrical Wires & Cables','Switches & Circuit Breakers','Pipes & Fittings','Sanitary Fixtures','Aggregates','Adhesives & Binders','Fasteners','Safety & Protective Materials') AFTER material_name,
ADD COLUMN quantity INT(11) DEFAULT 0 AFTER category,
ADD COLUMN status ENUM('Available','In Use','Damaged','Low Stock') DEFAULT 'Available' AFTER unit,
ADD COLUMN material_price DECIMAL(10,2) DEFAULT 0.00 AFTER status,
ADD COLUMN low_stock_threshold INT(11) DEFAULT 10 AFTER material_price,
ADD COLUMN max_stock INT(11) DEFAULT 100 AFTER low_stock_threshold;

-- Update existing records to have default values
UPDATE suppliers_materials SET 
    category = 'Fasteners' WHERE category IS NULL,
    quantity = 0 WHERE quantity IS NULL,
    status = 'Available' WHERE status IS NULL,
    material_price = price WHERE material_price IS NULL,
    low_stock_threshold = 10 WHERE low_stock_threshold IS NULL,
    max_stock = 100 WHERE max_stock IS NULL; 