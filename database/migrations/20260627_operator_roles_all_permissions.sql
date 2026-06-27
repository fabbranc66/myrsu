DELETE pr
FROM permission_role pr
INNER JOIN roles r ON r.id = pr.role_id
WHERE r.name IN ('membro', 'delegato', 'rls');

INSERT IGNORE INTO permission_role (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON r.name IN ('delegato', 'rls');
