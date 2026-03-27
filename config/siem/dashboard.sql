-- ============================================================
-- 3FA SIEM Dashboard — Requêtes exportables
-- À exécuter dans phpMyAdmin ou tout client MySQL
-- ============================================================

-- [W-01] Connexions réussies sur les 24 dernières heures
SELECT COUNT(*) AS connexions_reussies
FROM logs
WHERE status = 'success' AND date >= NOW() - INTERVAL 24 HOUR;

-- [W-02] Échecs de connexion sur les 24 dernières heures
SELECT COUNT(*) AS echecs_connexion
FROM logs
WHERE status = 'fail' AND date >= NOW() - INTERVAL 24 HOUR;

-- [W-03] Comptes actuellement bloqués
SELECT id, email, blocked_until
FROM users
WHERE blocked = 1
ORDER BY blocked_until DESC;

-- [W-04] IP distinctes sur les 7 derniers jours
SELECT COUNT(DISTINCT ip) AS ip_distinctes
FROM logs
WHERE date >= NOW() - INTERVAL 7 DAY;

-- [W-05] Timeline des événements (par heure, dernières 24h)
SELECT
    DATE_FORMAT(date, '%Y-%m-%d %H:00') AS heure,
    action,
    COUNT(*) AS nb_evenements
FROM audit_logs
WHERE date >= NOW() - INTERVAL 24 HOUR
GROUP BY heure, action
ORDER BY heure ASC;

-- [W-06] Top 10 IP avec le plus d'échecs (dernières 24h)
SELECT
    ip,
    COUNT(*) AS nb_echecs
FROM logs
WHERE status = 'fail' AND date >= NOW() - INTERVAL 24 HOUR
GROUP BY ip
ORDER BY nb_echecs DESC
LIMIT 10;

-- [RULE-001] Détection brute force : 3+ échecs en 5 min
SELECT
    user_id,
    COUNT(*) AS tentatives,
    MIN(date) AS premiere_tentative,
    MAX(date) AS derniere_tentative
FROM audit_logs
WHERE action = 'LOGIN_FAIL'
  AND date >= NOW() - INTERVAL 5 MINUTE
GROUP BY user_id
HAVING tentatives >= 3;

-- [RULE-002] Connexions depuis nouvelle IP
SELECT
    l.user_id,
    u.email,
    l.ip,
    l.date AS date_connexion
FROM logs l
JOIN users u ON u.id = l.user_id
WHERE l.status = 'success'
  AND NOT EXISTS (
      SELECT 1 FROM logs l2
      WHERE l2.user_id = l.user_id
        AND l2.ip = l.ip
        AND l2.date < l.date
  )
ORDER BY l.date DESC
LIMIT 50;

-- [AUDIT] Journal complet des 100 dernières actions
SELECT
    al.id,
    u.email,
    al.action,
    al.detail,
    al.ip,
    al.date
FROM audit_logs al
LEFT JOIN users u ON u.id = al.user_id
ORDER BY al.date DESC
LIMIT 100;

-- [EXPORT] Export CSV complet (à adapter selon votre client MySQL)
-- SELECT * FROM audit_logs INTO OUTFILE '/tmp/audit_export.csv'
-- FIELDS TERMINATED BY ',' ENCLOSED BY '"'
-- LINES TERMINATED BY '\n';
