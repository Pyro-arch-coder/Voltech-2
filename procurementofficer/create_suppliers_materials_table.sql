-- Create suppliers_materials table
CREATE TABLE IF NOT EXISTS suppliers_materials (
    id INT(11) NOT NULL AUTO_INCREMENT,
    supplier_id INT(11) NOT NULL,
    material_name VARCHAR(255) NOT NULL,
    quantity INT(11) DEFAULT 0,
    unit ENUM('kg','g','t','m³','ft³','L','mL','m','mm','cm','ft','in','pcs','bndl','rl','set','sack/bag','m²','ft²') NOT NULL,
    status ENUM('Available','In Use','Damaged','Low Stock') DEFAULT 'Available',
    material_price DECIMAL(10,2) DEFAULT 0.00,
    low_stock_threshold INT(11) DEFAULT 10,
    max_stock INT(11) DEFAULT 100,
    lead_time INT(11) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY supplier_id (supplier_id),
    CONSTRAINT fk_supplier_materials_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; 