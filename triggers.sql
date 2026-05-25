DELIMITER ;;

CREATE TRIGGER trg_order_status_change
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
  IF NEW.status != OLD.status THEN
    INSERT INTO order_status_log (order_id, status, changed_at)
    VALUES (NEW.id, NEW.status, NOW());
  END IF;
END;;

CREATE TRIGGER trg_order_status_insert
AFTER INSERT ON orders
FOR EACH ROW
BEGIN
  INSERT INTO order_status_log (order_id, status, changed_at)
  VALUES (NEW.id, NEW.status, NOW());
END;;

DELIMITER ;