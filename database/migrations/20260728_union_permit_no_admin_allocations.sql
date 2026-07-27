DELETE a
FROM union_permit_allocations a
INNER JOIN role_user ru ON ru.user_id = a.user_id
INNER JOIN roles r ON r.id = ru.role_id
WHERE r.name = 'admin';
