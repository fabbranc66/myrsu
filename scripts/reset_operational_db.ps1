$mysql = 'C:\xampp2\mysql\bin\mysql.exe'
if (!(Test-Path $mysql)) {
    $mysql = 'C:\xampp\mysql\bin\mysql.exe'
}

Get-Content "$PSScriptRoot\reset_operational_db.sql" -Raw | & $mysql -u root myrsu

& $mysql -u root -e "
USE myrsu;
SELECT 'users' table_name, COUNT(*) total FROM users
UNION ALL SELECT 'roles', COUNT(*) FROM roles
UNION ALL SELECT 'role_user', COUNT(*) FROM role_user
UNION ALL SELECT 'gdpr_consents', COUNT(*) FROM gdpr_consents
UNION ALL SELECT 'institutional_contacts', COUNT(*) FROM institutional_contacts
UNION ALL SELECT 'documents', COUNT(*) FROM documents
UNION ALL SELECT 'protocol_entries', COUNT(*) FROM protocol_entries
UNION ALL SELECT 'reports', COUNT(*) FROM reports
UNION ALL SELECT 'union_meetings', COUNT(*) FROM union_meetings
UNION ALL SELECT 'workers_assemblies', COUNT(*) FROM workers_assemblies
UNION ALL SELECT 'votings', COUNT(*) FROM votings
UNION ALL SELECT 'union_permit_requests', COUNT(*) FROM union_permit_requests
UNION ALL SELECT 'activity_logs', COUNT(*) FROM activity_logs;
"
