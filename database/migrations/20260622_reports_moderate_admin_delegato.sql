DELETE pr FROM permission_role pr
INNER JOIN permissions p ON p.id = pr.permission_id
INNER JOIN roles r ON r.id = pr.role_id
WHERE p.name = 'reports.moderate' AND r.name = 'rls';

INSERT IGNORE INTO permission_role (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON r.name = 'delegato'
WHERE p.name = 'reports.moderate';
