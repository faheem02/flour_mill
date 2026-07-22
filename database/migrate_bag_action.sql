-- Add bag_action to booking_bags: return or purchase
ALTER TABLE booking_bags ADD COLUMN bag_action ENUM('return','purchase') DEFAULT 'return' AFTER ownership;
