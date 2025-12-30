<?php
require_once __DIR__ . '/auth_helper.php';

[$pdo, $employer] = require_employer();

/**
 * Total companies
 */
$total_companies = $pdo->prepare("
    SELECT COUNT(*) 
    FROM companies 
    WHERE employer_id = ?
");
$total_companies->execute([$employer['id']]);
$total_companies = (int)$total_companies->fetchColumn();

/**
 * Total jobs
 */
$total_jobs = $pdo->prepare("
    SELECT COUNT(*) 
    FROM jobs j
    JOIN companies c ON c.id = j.company_id
    WHERE c.employer_id = ?
");
$total_jobs->execute([$employer['id']]);
$total_jobs = (int)$total_jobs->fetchColumn();

/**
 * Active jobs
 */
$active_jobs = $pdo->prepare("
    SELECT COUNT(*) 
    FROM jobs j
    JOIN companies c ON c.id = j.company_id
    WHERE c.employer_id = ?
      AND j.status = 'published'
");
$active_jobs->execute([$employer['id']]);
$active_jobs = (int)$active_jobs->fetchColumn();

/**
 * Closed jobs
 */
$closed_jobs = $pdo->prepare("
    SELECT COUNT(*) 
    FROM jobs j
    JOIN companies c ON c.id = j.company_id
    WHERE c.employer_id = ?
      AND j.status = 'closed'
");
$closed_jobs->execute([$employer['id']]);
$closed_jobs = (int)$closed_jobs->fetchColumn();

/**
 * Total applications
 */
$total_applications = $pdo->prepare("
    SELECT COUNT(*) 
    FROM applications a
    JOIN jobs j ON j.id = a.job_id
    JOIN companies c ON c.id = j.company_id
    WHERE c.employer_id = ?
");
$total_applications->execute([$employer['id']]);
$total_applications = (int)$total_applications->fetchColumn();

/**
 * Shortlisted candidates
 */
$shortlisted = $pdo->prepare("
    SELECT COUNT(*) 
    FROM applications a
    JOIN jobs j ON j.id = a.job_id
    JOIN companies c ON c.id = j.company_id
    WHERE c.employer_id = ?
      AND a.status = 'shortlisted'
");
$shortlisted->execute([$employer['id']]);
$shortlisted = (int)$shortlisted->fetchColumn();

/**
 * Interviews scheduled
 */
$interviews = $pdo->prepare("
    SELECT COUNT(*) 
    FROM interviews i
    JOIN applications a ON a.id = i.applicant_id
    JOIN jobs j ON j.id = a.job_id
    JOIN companies c ON c.id = j.company_id
    WHERE c.employer_id = ?
");
$interviews->execute([$employer['id']]);
$interviews = (int)$interviews->fetchColumn();

json_response([
    'status' => true,
    'dashboard' => [
        'companies'            => $total_companies,
        'total_jobs'           => $total_jobs,
        'active_jobs'          => $active_jobs,
        'closed_jobs'          => $closed_jobs,
        'applications'         => $total_applications,
        'shortlisted'          => $shortlisted,
        'interviews_scheduled' => $interviews
    ]
]);
