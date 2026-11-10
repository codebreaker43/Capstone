<?php
// CSU CEIT - Interactive Platform for Capstone Project Oral Defense
// Homepage with Live Interactive Defense & Adviser Management Showcase

$db_connected = false;
$stats = [
    'students' => 120,
    'advisers' => 18,
    'defenses' => 45,
    'satisfaction' => '99.4%'
];

if (file_exists(__DIR__ . '/thesis_system/db.php')) {
    try {
        @include_once __DIR__ . '/thesis_system/db.php';
        if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
            $db_connected = true;
            $stu_q = $conn->query("SELECT COUNT(*) as c FROM user WHERE role='student'");
            if ($stu_q && $r = $stu_q->fetch_assoc()) $stats['students'] = max((int)$r['c'], $stats['students']);
            
            $adv_q = $conn->query("SELECT COUNT(*) as c FROM user WHERE role='adviser'");
            if ($adv_q && $r = $adv_q->fetch_assoc()) $stats['advisers'] = max((int)$r['c'], $stats['advisers']);
            
            $def_q = $conn->query("SELECT COUNT(*) as c FROM defense_meetings");
            if ($def_q && $r = $def_q->fetch_assoc()) $stats['defenses'] = max((int)$r['c'], $stats['defenses']);
        }
    } catch (Exception $e) {
        // Fallback to static metrics
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSU CEIT | Interactive Platform for Capstone Project Oral Defense</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --nav-green: #064e3b;
            --nav-green-dark: #022c22;
            --accent-green: #059669;
            --accent-green-light: #10b981;
            --accent-green-hover: #047857;
            --csu-gold: #f59e0b;
            --csu-gold-light: #fbbf24;
            --csu-gold-dark: #d97706;
            --text-heading: #0f172a;
            --text-body: #334155;
            --text-muted: #64748b;
            --border-light: #e2e8f0;
            --bg-light: #f8fafc;
            --bg-card: #ffffff;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 14px -2px rgba(0,0,0,0.08);
            --shadow-lg: 0 16px 36px -6px rgba(0,0,0,0.12);
            --shadow-glow: 0 0 25px rgba(16, 185, 129, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        section, main {
            scroll-margin-top: 80px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text-body);
            background-color: #ffffff;
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* ==========================================================================
           HEADER NAVBAR
           ========================================================================== */
        .header-navbar {
            background: linear-gradient(180deg, #004d20 0%, #003615 100%);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            color: #ffffff;
            padding: 0.85rem clamp(1.25rem, 3vw, 3rem);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
            border-bottom: 4px solid #ea580c;
            width: 100%;
        }

        .brand-container {
            display: flex;
            align-items: center;
            gap: 0.95rem;
            text-decoration: none;
            color: #ffffff;
            transition: transform 0.2s ease;
            flex-shrink: 0;
        }

        .brand-container:hover {
            transform: scale(1.02);
        }

        .brand-logo-wrap {
            position: relative;
        }

        .brand-logo {
            width: 48px;
            height: 48px;
            object-fit: contain;
            filter: drop-shadow(0 2px 6px rgba(0,0,0,0.3));
            background: rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            padding: 3px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .brand-ai-pulse {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 11px;
            height: 11px;
            background-color: var(--csu-gold);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--csu-gold);
            animation: pulseGlow 2s infinite;
        }

        @keyframes pulseGlow {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.35); opacity: 0.7; }
        }

        .brand-text {
            display: flex;
            flex-direction: column;
        }

        .brand-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.18rem;
            letter-spacing: 0.5px;
            line-height: 1.15;
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }

        .brand-title span.badge-ai {
            background: rgba(245, 158, 11, 0.2);
            color: var(--csu-gold-light);
            border: 1px solid rgba(245, 158, 11, 0.45);
            font-size: 0.65rem;
            padding: 2px 7px;
            border-radius: 5px;
            font-weight: 800;
            letter-spacing: 0.8px;
        }

        .brand-subtitle {
            font-size: 0.74rem;
            color: #a7f3d0;
            letter-spacing: 0.3px;
            font-weight: 500;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            list-style: none;
            margin-left: auto;
            margin-right: 1.75rem;
        }

        .nav-menu a {
            color: #d1fae5;
            text-decoration: none;
            font-size: 0.92rem;
            font-weight: 600;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            padding: 0.45rem 0.85rem;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
        }

        .nav-menu a:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-1px);
        }

        .nav-menu a::after {
            content: '';
            position: absolute;
            bottom: 2px;
            left: 0.85rem;
            right: 0.85rem;
            height: 2px;
            background: var(--csu-gold);
            transform: scaleX(0);
            transition: transform 0.25s ease;
            border-radius: 2px;
        }

        .nav-menu a:hover::after {
            transform: scaleX(1);
        }

        .nav-right-action {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
        }

        .btn-nav-action {
            background-color: #ffffff;
            color: var(--nav-green);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 800;
            padding: 0.55rem 1.35rem;
            border-radius: 9999px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.16);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.25);
        }

        .btn-nav-action:hover {
            background-color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 4px 18px rgba(245, 158, 11, 0.35);
            color: var(--accent-green-hover);
        }

        .btn-nav-action i {
            color: var(--csu-gold);
            transition: transform 0.2s ease;
        }

        .btn-nav-action:hover i {
            transform: translateX(2px);
        }

        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: #ffffff;
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* ==========================================================================
           HERO SECTION
           ========================================================================== */
        .hero-section {
            position: relative;
            min-height: 84vh;
            width: 100%;
            display: flex;
            align-items: center;
            background-color: #ffffff;
            background-image: 
                linear-gradient(to right, 
                    rgba(255, 255, 255, 0.96) 0%, 
                    rgba(255, 255, 255, 0.92) 34%, 
                    rgba(255, 255, 255, 0.78) 50%, 
                    rgba(255, 255, 255, 0.4) 72%, 
                    rgba(255, 255, 255, 0.1) 88%, 
                    transparent 100%
                ),
                url('thesis_system/bg.jpg');
            background-position: right center;
            background-size: cover;
            background-repeat: no-repeat;
            padding: 3.5rem 5% 3rem;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -10%;
            left: -5%;
            width: 550px;
            height: 550px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.12) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 1;
        }

        .hero-container {
            width: 100%;
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 2;
            gap: 2.75rem;
        }

        .hero-content {
            flex: 0 0 54%;
            max-width: 630px;
            animation: fadeInHero 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeInHero {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            background: #ecfdf5;
            color: var(--accent-green);
            border: 1px solid #a7f3d0;
            padding: 0.35rem 0.95rem;
            border-radius: 9999px;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 1.15rem;
            box-shadow: var(--shadow-sm);
        }

        .hero-tag i {
            color: var(--csu-gold);
            font-size: 0.88rem;
        }

        .hero-title {
            font-family: 'Outfit', sans-serif;
            font-size: clamp(2rem, 3vw, 2.75rem);
            font-weight: 900;
            line-height: 1.18;
            color: var(--text-heading);
            margin-bottom: 1.25rem;
            letter-spacing: -0.025em;
        }

        .hero-title .gradient-text {
            background: linear-gradient(135deg, var(--nav-green) 0%, var(--accent-green) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 1.05rem;
            color: #475569;
            line-height: 1.65;
            margin-bottom: 2rem;
            max-width: 550px;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }

        .btn-primary-action {
            background: linear-gradient(135deg, var(--accent-green) 0%, var(--accent-green-hover) 100%);
            color: #ffffff;
            text-decoration: none;
            font-size: 0.96rem;
            font-weight: 700;
            padding: 0.85rem 1.95rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            transition: all 0.28s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.35);
        }

        .btn-primary-action:hover {
            transform: translateY(-2.5px);
            box-shadow: 0 8px 24px rgba(5, 150, 105, 0.45);
            background: linear-gradient(135deg, var(--accent-green-light) 0%, var(--accent-green) 100%);
        }

        .btn-primary-action i {
            transition: transform 0.2s ease;
        }

        .btn-primary-action:hover i {
            transform: translateX(4px);
        }

        .btn-secondary-action {
            background-color: #ffffff;
            color: #334155;
            border: 1.5px solid #cbd5e1;
            text-decoration: none;
            font-size: 0.96rem;
            font-weight: 700;
            padding: 0.85rem 1.75rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            transition: all 0.25s ease;
            box-shadow: var(--shadow-sm);
        }

        .btn-secondary-action:hover {
            background-color: #f8fafc;
            border-color: var(--accent-green);
            color: var(--accent-green-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .hero-metrics {
            display: flex;
            align-items: center;
            gap: 1.75rem;
            flex-wrap: wrap;
            padding-top: 1.35rem;
            border-top: 1px solid rgba(226, 232, 240, 0.9);
        }

        .metric-item {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            font-size: 0.86rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .metric-item i {
            color: var(--accent-green);
            font-size: 0.95rem;
        }

        /* ==========================================================================
           RIGHT SIDE: INTERACTIVE ORAL DEFENSE & AI MATCHING HERO CARD
           ========================================================================== */
        .hero-interactive-card {
            flex: 0 0 42%;
            max-width: 470px;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-radius: 22px;
            padding: 1.85rem;
            border: 1.5px solid rgba(255, 255, 255, 0.95);
            box-shadow: 0 22px 50px -12px rgba(6, 78, 59, 0.22), 0 0 0 1px rgba(16, 185, 129, 0.18);
            animation: floatCard 6s ease-in-out infinite;
            position: relative;
        }

        @keyframes floatCard {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .defense-card-tabs {
            display: flex;
            gap: 0.45rem;
            background: #f1f5f9;
            padding: 0.35rem;
            border-radius: 12px;
            margin-bottom: 1.25rem;
        }

        .def-tab-btn {
            flex: 1;
            padding: 0.45rem 0.5rem;
            border-radius: 9px;
            border: none;
            background: transparent;
            font-size: 0.76rem;
            font-weight: 800;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }

        .def-tab-btn.active {
            background: #ffffff;
            color: #064e3b;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }

        .defense-live-session {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.15rem;
            margin-bottom: 1.1rem;
            box-shadow: var(--shadow-sm);
        }

        .session-stage-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .stage-badge-pill {
            background: #e0f2fe;
            color: #0369a1;
            font-size: 0.72rem;
            font-weight: 800;
            padding: 0.2rem 0.65rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .session-status-live {
            background: #ecfdf5;
            color: #047857;
            font-size: 0.72rem;
            font-weight: 800;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .session-status-live .pulse-dot {
            width: 7px;
            height: 7px;
            background: #10b981;
            border-radius: 50%;
            animation: pulseGlow 1.5s infinite;
        }

        .defense-title-display {
            font-size: 0.94rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.35;
            margin-bottom: 0.6rem;
        }

        .defense-candidate-info {
            font-size: 0.8rem;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Panel Committee Deliberation Strip */
        .deliberation-strip {
            background: linear-gradient(135deg, #022c22 0%, #064e3b 100%);
            color: #ffffff;
            border-radius: 14px;
            padding: 1.15rem;
            box-shadow: 0 8px 20px rgba(2, 44, 34, 0.3);
        }

        .deliberation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .deliberation-header span {
            font-size: 0.72rem;
            color: #a7f3d0;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .deliberation-verdict {
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--csu-gold-light);
            background: rgba(245, 158, 11, 0.18);
            border: 1px solid rgba(245, 158, 11, 0.35);
            padding: 0.2rem 0.65rem;
            border-radius: 999px;
        }

        .deliberation-features-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .delib-stat-tile {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 9px;
            padding: 0.5rem;
            text-align: center;
        }

        .delib-stat-tile .num {
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1;
        }

        .delib-stat-tile .lbl {
            font-size: 0.65rem;
            color: #a7f3d0;
            font-weight: 600;
            margin-top: 0.2rem;
        }

        /* ==========================================================================
           SECTIONS GENERAL
           ========================================================================== */
        .section {
            padding: 5.5rem 5%;
        }

        .section-light {
            background-color: var(--bg-light);
            border-top: 1px solid var(--border-light);
            border-bottom: 1px solid var(--border-light);
        }

        .section-header {
            text-align: center;
            max-width: 760px;
            margin: 0 auto 3.5rem;
        }

        .section-badge {
            color: var(--accent-green);
            font-size: 0.84rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 0.5rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title {
            font-family: 'Outfit', sans-serif;
            font-size: clamp(1.9rem, 3.2vw, 2.65rem);
            font-weight: 900;
            color: var(--text-heading);
            line-height: 1.2;
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
        }

        .section-desc {
            font-size: 1.05rem;
            color: var(--text-muted);
        }

        /* ==========================================================================
           INTERACTIVE ORAL DEFENSE & MCDA MATCHER SHOWCASE
           ========================================================================== */
        .interactive-showcase-box {
            max-width: 1060px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 22px;
            border: 1px solid var(--border-light);
            padding: 2.25rem;
            box-shadow: var(--shadow-lg);
        }

        .showcase-selector-bar {
            display: flex;
            gap: 0.65rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
            justify-content: center;
        }

        .btn-showcase-filter {
            background: #f1f5f9;
            color: var(--text-body);
            border: 1.5px solid #e2e8f0;
            padding: 0.65rem 1.25rem;
            border-radius: 9999px;
            font-size: 0.86rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-showcase-filter:hover, .btn-showcase-filter.active {
            background: var(--nav-green);
            color: #ffffff;
            border-color: var(--nav-green);
            box-shadow: 0 4px 14px rgba(6, 78, 59, 0.28);
            transform: translateY(-1px);
        }

        .defense-stages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .stage-interactive-card {
            border: 1.5px solid var(--border-light);
            border-radius: 16px;
            padding: 1.5rem;
            background: #ffffff;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }

        .stage-interactive-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: var(--accent-green-light);
        }

        .stage-interactive-card.featured {
            border-color: var(--accent-green);
            box-shadow: 0 10px 28px rgba(16, 185, 129, 0.16);
            background: linear-gradient(to bottom, #f0fdf4 0%, #ffffff 38%);
        }

        .stage-ribbon {
            position: absolute;
            top: 14px;
            right: 14px;
            background: var(--accent-green);
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 800;
            padding: 0.25rem 0.65rem;
            border-radius: 999px;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .stage-interactive-card h4 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-heading);
            margin: 0;
            line-height: 1.3;
        }

        .stage-interactive-card p {
            font-size: 0.88rem;
            color: #475569;
            line-height: 1.5;
            margin: 0;
        }

        .stage-feature-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            margin-top: 0.25rem;
        }

        .stage-feature-list li {
            font-size: 0.82rem;
            color: var(--text-body);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }

        .stage-feature-list li i {
            color: var(--accent-green);
            font-size: 0.8rem;
        }

        /* ==========================================================================
           CORE CAPSTONE ORAL DEFENSE PLATFORM CAPABILITIES
           ========================================================================== */
        .capabilities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 2rem;
            max-width: 1240px;
            margin: 0 auto;
        }

        .capability-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 2.25rem 2rem;
            border: 1.5px solid var(--border-light);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .capability-card:hover {
            transform: translateY(-6px);
            border-color: #6ee7b7;
            box-shadow: var(--shadow-lg);
        }

        .cap-icon-box {
            width: 58px;
            height: 58px;
            border-radius: 16px;
            background: #ecfdf5;
            color: var(--accent-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.65rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        .capability-card h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.28rem;
            font-weight: 800;
            color: var(--text-heading);
            margin-bottom: 0.75rem;
        }

        .capability-card p {
            color: var(--text-body);
            font-size: 0.94rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
            flex-grow: 1;
        }

        .capability-card a {
            color: var(--accent-green);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: gap 0.2s ease;
        }

        .capability-card a:hover {
            gap: 0.6rem;
            color: var(--accent-green-hover);
        }

        /* ==========================================================================
           END-TO-END ORAL DEFENSE WORKFLOW PIPELINE
           ========================================================================== */
        .workflow-timeline {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .workflow-node {
            text-align: center;
            padding: 2.5rem 1.75rem 1.75rem;
            background: #ffffff;
            border-radius: 20px;
            border: 1.5px solid var(--border-light);
            box-shadow: var(--shadow-sm);
            position: relative;
            transition: transform 0.3s ease;
        }

        .workflow-node:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .workflow-step-badge {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, var(--nav-green) 0%, var(--accent-green) 100%);
            color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 900;
            font-family: 'Outfit', sans-serif;
            margin: -4rem auto 1.35rem;
            box-shadow: 0 6px 18px rgba(6, 78, 59, 0.3);
            border: 3px solid #ffffff;
        }

        .workflow-node h4 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-heading);
            margin-bottom: 0.65rem;
        }

        .workflow-node p {
            font-size: 0.88rem;
            color: var(--text-muted);
            line-height: 1.55;
        }

        /* ==========================================================================
           FOOTER
           ========================================================================== */
        .footer {
            background: linear-gradient(180deg, #071722 0%, #030d14 100%);
            color: #94a3b8;
            padding: 2.75rem 5% 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 3rem;
            max-width: 1240px;
            margin: 0 auto 2.5rem;
        }

        .footer-brand {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            margin-bottom: 1rem;
        }

        .footer-brand img {
            width: 44px;
            height: 44px;
            object-fit: contain;
        }

        .footer-brand span {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            font-weight: 800;
            color: #ffffff;
        }

        .footer-desc {
            font-size: 0.88rem;
            line-height: 1.6;
            color: #94a3b8;
            max-width: 420px;
        }

        .footer-col h5 {
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1.1rem;
        }

        .footer-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }

        .footer-links a {
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.86rem;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: #34d399;
        }

        .footer-bottom {
            max-width: 1240px;
            margin: 0 auto;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.82rem;
            color: #64748b;
        }

        /* Mobile Drawer & Backdrop */
        .mobile-nav-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.65);
            z-index: 998;
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .mobile-nav-backdrop.active {
            display: block;
            opacity: 1;
        }

        .mobile-nav-drawer {
            display: none;
            position: fixed;
            top: 60px;
            left: 0;
            right: 0;
            background: linear-gradient(180deg, #022c22 0%, #00471b 100%);
            padding: 1.25rem 1.25rem 1.75rem;
            box-shadow: 0 16px 36px rgba(0,0,0,0.45);
            z-index: 999;
            flex-direction: column;
            gap: 0.5rem;
            border-bottom: 4px solid #ea580c;
            transform: translateY(-20px);
            opacity: 0;
            pointer-events: none;
            transition: all 0.28s cubic-bezier(0.4, 0, 0.2, 1);
            max-height: calc(100vh - 60px);
            overflow-y: auto;
        }

        .mobile-nav-drawer.active {
            display: flex;
            transform: translateY(0);
            opacity: 1;
            pointer-events: auto;
        }

        .mobile-nav-drawer a {
            color: #ffffff;
            text-decoration: none;
            font-size: 0.98rem;
            font-weight: 600;
            padding: 0.75rem 0.85rem;
            border-radius: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.2s;
        }

        .mobile-nav-drawer a:hover,
        .mobile-nav-drawer a:active {
            background: rgba(255, 255, 255, 0.12);
            color: #a7f3d0;
            padding-left: 1.1rem;
        }

        .btn-nav-text-short {
            display: none;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1024px) {
            .hero-container {
                flex-direction: column;
                text-align: center;
            }
            .hero-content {
                flex: 0 0 100%;
                max-width: 100%;
            }
            .hero-subtitle {
                margin: 0 auto 2rem;
            }
            .hero-actions {
                justify-content: center;
            }
            .hero-metrics {
                justify-content: center;
            }
            .hero-interactive-card {
                flex: 0 0 100%;
                width: 100%;
                max-width: 500px;
            }
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }

        @media (max-width: 768px) {
            .header-navbar {
                padding: 0.65rem 1rem;
            }

            .brand-container {
                gap: 0.65rem;
                max-width: calc(100% - 55px);
            }

            .brand-logo {
                width: 38px;
                height: 38px;
                border-radius: 10px;
            }

            .brand-title {
                font-size: 1rem;
                gap: 0.35rem;
                white-space: nowrap;
            }

            .brand-title span.badge-ai {
                font-size: 0.58rem;
                padding: 1px 5px;
            }

            .brand-subtitle {
                display: none; /* Hide long subtitle to prevent navbar overflow */
            }

            .nav-menu {
                display: none;
            }

            .nav-right-action {
                gap: 0.45rem;
            }

            .btn-nav-action {
                display: none !important; /* Removed Portal button from top navbar on mobile */
            }

            .mobile-menu-toggle {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 38px;
                height: 38px;
                border-radius: 9px;
                background: rgba(255, 255, 255, 0.12);
                border: 1px solid rgba(255, 255, 255, 0.22);
                font-size: 1.15rem;
                color: #ffffff;
                cursor: pointer;
                transition: all 0.2s;
            }

            .mobile-menu-toggle:active {
                background: rgba(255, 255, 255, 0.25);
                transform: scale(0.95);
            }

            /* Hero on mobile */
            .hero-section {
                padding: 2.5rem 1.15rem 2.75rem;
                min-height: auto;
            }

            .hero-title {
                font-size: clamp(1.65rem, 6.2vw, 2.25rem);
                line-height: 1.22;
                margin-bottom: 1rem;
            }

            .hero-subtitle {
                font-size: 0.94rem;
                line-height: 1.6;
                margin-bottom: 1.5rem;
            }

            .hero-actions {
                flex-direction: column;
                width: 100%;
                gap: 0.75rem;
                margin-bottom: 1.5rem;
            }

            .btn-primary-action,
            .btn-secondary-action {
                width: 100%;
                justify-content: center;
                padding: 0.85rem 1.25rem;
                font-size: 0.92rem;
            }

            .hero-metrics {
                flex-direction: column;
                align-items: stretch;
                gap: 0.65rem;
                padding-top: 1rem;
            }

            .metric-item {
                justify-content: center;
                font-size: 0.82rem;
            }

            /* Hero interactive card */
            .hero-interactive-card {
                padding: 1.25rem 1rem;
                border-radius: 18px;
                max-width: 100%;
                animation: none;
            }

            .defense-card-tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                gap: 0.35rem;
                padding: 0.3rem;
                margin-bottom: 1rem;
            }

            .defense-card-tabs::-webkit-scrollbar {
                display: none;
            }

            .def-tab-btn {
                white-space: nowrap;
                flex-shrink: 0;
                padding: 0.45rem 0.75rem;
                font-size: 0.75rem;
            }

            .defense-live-session {
                padding: 0.95rem;
                border-radius: 12px;
                margin-bottom: 0.85rem;
            }

            .defense-title-display {
                font-size: 0.88rem;
                line-height: 1.35;
            }

            .deliberation-strip {
                padding: 0.95rem;
                border-radius: 12px;
            }

            .deliberation-header {
                flex-wrap: wrap;
                gap: 0.4rem;
            }

            .deliberation-verdict {
                font-size: 0.82rem;
                padding: 0.15rem 0.55rem;
            }

            .deliberation-features-row {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.4rem;
            }

            .delib-stat-tile {
                padding: 0.45rem 0.25rem;
                border-radius: 8px;
            }

            .delib-stat-tile .num {
                font-size: 0.92rem;
            }

            .delib-stat-tile .lbl {
                font-size: 0.62rem;
            }

            /* Sections on mobile */
            .section {
                padding: 3.25rem 1.15rem;
            }

            .section-header {
                margin-bottom: 2rem;
            }

            .section-title {
                font-size: clamp(1.55rem, 5.2vw, 2.15rem);
            }

            .section-desc {
                font-size: 0.92rem;
            }

            .interactive-showcase-box {
                padding: 1.25rem 0.95rem;
                border-radius: 18px;
            }

            .showcase-selector-bar {
                justify-content: flex-start;
                overflow-x: auto;
                flex-wrap: nowrap;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                padding-bottom: 0.4rem;
                gap: 0.5rem;
            }

            .showcase-selector-bar::-webkit-scrollbar {
                display: none;
            }

            .btn-showcase-filter {
                white-space: nowrap;
                flex-shrink: 0;
                font-size: 0.78rem;
                padding: 0.45rem 0.85rem;
            }

            .defense-stages-grid,
            .capabilities-grid,
            .workflow-timeline {
                grid-template-columns: 1fr;
                gap: 1.25rem;
            }

            .stage-interactive-card {
                padding: 1.25rem 1.1rem;
            }

            .capability-card {
                padding: 1.5rem 1.25rem;
                border-radius: 16px;
            }

            .workflow-node {
                padding: 2.25rem 1.25rem 1.25rem;
                border-radius: 16px;
            }

            .footer {
                padding: 2.25rem 1.15rem 1.5rem;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .footer-bottom {
                flex-direction: column;
                text-align: center;
                gap: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .header-navbar {
                padding: 0.55rem 0.75rem;
            }

            .brand-container {
                max-width: calc(100% - 110px);
            }

            .brand-logo {
                width: 34px;
                height: 34px;
            }

            .brand-title {
                font-size: 0.92rem;
            }

            .brand-title span.badge-ai {
                display: none;
            }

            .btn-nav-action {
                padding: 0.4rem 0.65rem;
                font-size: 0.75rem;
            }

            .mobile-menu-toggle {
                width: 34px;
                height: 34px;
                font-size: 1.05rem;
            }

            .hero-title {
                font-size: 1.5rem;
            }

            .delib-stat-tile .num {
                font-size: 0.82rem;
            }

            .delib-stat-tile .lbl {
                font-size: 0.56rem;
            }
        }
    </style>
</head>
<body>

    <!-- ==========================================
         TOP HEADER NAVBAR
         ========================================== -->
    <header class="header-navbar">
        <a href="#" class="brand-container">
            <div class="brand-logo-wrap">
                <img src="thesis_system/logo.png" alt="CSU CEIT Logo" class="brand-logo">
                <span class="brand-ai-pulse" title="System Engine Active"></span>
            </div>
            <div class="brand-text">
                <div class="brand-title">
                    CSU CEIT <span class="badge-ai">DEFENSE PLATFORM</span>
                </div>
                <span class="brand-subtitle">Interactive Platform for Capstone Project Oral Defense</span>
            </div>
        </a>

        <!-- Navigation Links -->
        <ul class="nav-menu">
            <li><a href="#home"><i class="fa-solid fa-house" style="font-size: 0.8rem; color: var(--csu-gold);"></i> Home</a></li>
            <li><a href="#defense-hub"><i class="fa-solid fa-graduation-cap" style="font-size: 0.8rem; color: var(--csu-gold);"></i> Oral Defense Hub</a></li>
            <li><a href="#capabilities"><i class="fa-solid fa-layer-group" style="font-size: 0.8rem; color: var(--csu-gold);"></i> Platform Features</a></li>
            <li><a href="#workflow"><i class="fa-solid fa-diagram-project" style="font-size: 0.8rem; color: var(--csu-gold);"></i> Defense Lifecycle</a></li>
        </ul>

        <!-- Action Button -->
        <div class="nav-right-action">
            <a href="thesis_system/login.php" class="btn-nav-action" title="Sign In">
                <i class="fa-solid fa-right-to-bracket"></i> Sign In
            </a>
            <button class="mobile-menu-toggle" id="mobileToggle" aria-label="Toggle navigation" title="Menu">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Mobile Backdrop -->
    <div class="mobile-nav-backdrop" id="mobileBackdrop" onclick="closeMobileMenu()"></div>

    <!-- Mobile Drawer -->
    <div class="mobile-nav-drawer" id="mobileDrawer">
        <a href="#home" onclick="closeMobileMenu()"><i class="fa-solid fa-house" style="color: var(--csu-gold);"></i> Home</a>
        <a href="#defense-hub" onclick="closeMobileMenu()"><i class="fa-solid fa-graduation-cap" style="color: var(--csu-gold);"></i> Oral Defense Hub</a>
        <a href="#capabilities" onclick="closeMobileMenu()"><i class="fa-solid fa-layer-group" style="color: var(--csu-gold);"></i> Platform Features</a>
        <a href="#workflow" onclick="closeMobileMenu()"><i class="fa-solid fa-diagram-project" style="color: var(--csu-gold);"></i> Defense Lifecycle</a>
        <a href="thesis_system/login.php"><i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In to Portal</a>
        <a href="thesis_system/login.php?signup=1"><i class="fa-solid fa-user-plus"></i> Create Student Account</a>
    </div>

    <!-- ==========================================
         HERO SECTION
         ========================================== -->
    <main class="hero-section" id="home">
        <div class="hero-container">
            <!-- Left Hero Content -->
            <div class="hero-content">
                <div class="hero-tag">
                    <i class="fa-solid fa-shield-halved"></i> OFFICIAL CEIT CAPSTONE ACADEMIC PLATFORM
                </div>

                <h1 class="hero-title">
                    Interactive Platform for<br>
                    <span class="gradient-text">Capstone Project Oral Defense</span>
                </h1>

                <p class="hero-subtitle">
                    Caraga State University Cabadbaran Campus CEIT's centralized academic portal. Seamlessly orchestrate research adviser matching, pre-acceptance consultations, committee evaluations, voice-recorded panel feedback, and official oral defense clearances.
                </p>

                <div class="hero-actions">
                    <a href="thesis_system/login.php" class="btn-primary-action">
                        Access Defense Portal <i class="fa-solid fa-arrow-right"></i>
                    </a>
                    <a href="#defense-hub" class="btn-secondary-action">
                        <i class="fa-solid fa-calendar-check"></i> Explore Defense Hub
                    </a>
                </div>

                <!-- Live Metrics Row -->
                <div class="hero-metrics">
                    <div class="metric-item">
                        <i class="fa-solid fa-calendar-days"></i>
                        <span>Proposal &amp; Final Defenses</span>
                    </div>
                    <div class="metric-item">
                        <i class="fa-solid fa-microphone-lines"></i>
                        <span>Live Voice Deliberations</span>
                    </div>
                    <div class="metric-item">
                        <i class="fa-solid fa-file-signature"></i>
                        <span>Automated MOM &amp; Rubrics</span>
                    </div>
                </div>
            </div>

            <!-- Right Hero Card: Interactive Oral Defense Preview -->
            <div class="hero-interactive-card">
                <div class="defense-card-tabs">
                    <button class="def-tab-btn active" onclick="switchHeroTab('it198')">Proposal Defense Title</button>
                    <button class="def-tab-btn" onclick="switchHeroTab('it199')">Final Defense</button>
                    <button class="def-tab-btn" onclick="switchHeroTab('consultation')">Adviser Consultation</button>
                </div>

                <!-- Dynamic Session Display Area -->
                <div id="heroSessionContainer">
                    <div class="defense-live-session">
                        <div class="session-stage-row">
                            <span class="stage-badge-pill" id="heroStageBadge">
                                <i class="fa-solid fa-award"></i> Proposal Defense Title
                            </span>
                            <span class="session-status-live">
                                <span class="pulse-dot"></span> Live Session
                            </span>
                        </div>
                        <div class="defense-title-display" id="heroTitleText">
                            "IoT and AI-Driven Smart Agriculture Monitoring and Solar Optimization"
                        </div>
                        <div class="defense-candidate-info" id="heroCandidateText">
                            <span><i class="fa-solid fa-users" style="color: #047857;"></i> Candidate: <strong>Group AgriTech (Leader: Vincent Jabla)</strong></span>
                            <span>&bull; CEIT Defense Hall</span>
                        </div>
                    </div>

                    <!-- Panel Deliberation Summary Strip -->
                    <div class="deliberation-strip">
                        <div class="deliberation-header">
                            <span><i class="fa-solid fa-users-viewfinder"></i> Examination Panel Status</span>
                            <div class="deliberation-verdict" id="heroVerdictText">Approved w/ Minor Revisions</div>
                        </div>

                        <div style="font-size: 0.8rem; color: #d1fae5; line-height: 1.4;" id="heroCommitteeText">
                            Committee: Dr. Joseph A. Vistal (Chair) &bull; Engr. R. Gomez &bull; Prof. C. Villareal
                        </div>

                        <div class="deliberation-features-row">
                            <div class="delib-stat-tile">
                                <div class="num"><?php echo $stats['defenses']; ?>+</div>
                                <div class="lbl">Defenses Scheduled</div>
                            </div>
                            <div class="delib-stat-tile">
                                <div class="num">100%</div>
                                <div class="lbl">Official Minutes (MOM)</div>
                            </div>
                            <div class="delib-stat-tile">
                                <div class="num"><?php echo $stats['advisers']; ?></div>
                                <div class="lbl">Faculty Advisers</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- ==========================================
         ORAL DEFENSE HUB & INTERACTIVE SHOWCASE
         ========================================== -->
    <section class="section section-light" id="defense-hub">
        <div class="section-header">
            <span class="section-badge"><i class="fa-solid fa-chalkboard-user"></i> Oral Defense Management</span>
            <h2 class="section-title">The Complete Capstone Oral Defense Ecosystem</h2>
            <p class="section-desc">Experience structured, multi-role academic management from proposal outlines to final capstone deliberations.</p>
        </div>

        <div class="interactive-showcase-box">
            <div class="showcase-selector-bar">
                <button class="btn-showcase-filter active" onclick="filterShowcase('all')">
                    <i class="fa-solid fa-border-all"></i> All Defense Modules
                </button>
                <button class="btn-showcase-filter" onclick="filterShowcase('it198')">
                    <i class="fa-solid fa-file-signature"></i> Proposal Defense Title
                </button>
                <button class="btn-showcase-filter" onclick="filterShowcase('it199')">
                    <i class="fa-solid fa-graduation-cap"></i> Final Defense
                </button>
                <button class="btn-showcase-filter" onclick="filterShowcase('consultation')">
                    <i class="fa-solid fa-clipboard-check"></i> Adviser Consultation
                </button>
            </div>

            <div class="defense-stages-grid" id="showcaseGrid">
                <!-- Card 1: Proposal Defense Title -->
                <div class="stage-interactive-card featured" data-cat="it198">
                    <span class="stage-ribbon"><i class="fa-solid fa-award"></i> Proposal Defense Title</span>
                    <h4>Proposal Defense &amp; Outline Verification</h4>
                    <p>Rigorous pre-oral assessment evaluating project problem statements, feasibility, methodology, and theoretical frameworks.</p>
                    <ul class="stage-feature-list">
                        <li><i class="fa-solid fa-check"></i> Standardized CEIT Oral Defense Rubric</li>
                        <li><i class="fa-solid fa-check"></i> Automated Committee Verdict Form</li>
                        <li><i class="fa-solid fa-check"></i> Voice-Recorded Panel Recommendations</li>
                    </ul>
                </div>

                <!-- Card 2: Final Defense -->
                <div class="stage-interactive-card" data-cat="it199">
                    <span class="stage-ribbon" style="background: #0284c7;"><i class="fa-solid fa-star"></i> Final Defense</span>
                    <h4>Final Capstone Oral Defense Deliberations</h4>
                    <p>Live examination panel evaluation of fully deployed hardware/software systems, ISO 25010 metrics, and final manuscripts.</p>
                    <ul class="stage-feature-list">
                        <li><i class="fa-solid fa-check"></i> Live Defense Secretary Minutes (MOM)</li>
                        <li><i class="fa-solid fa-check"></i> Individual Criteria Grading &amp; Averaging</li>
                        <li><i class="fa-solid fa-check"></i> Final University Archival Clearance</li>
                    </ul>
                </div>

                <!-- Card 3: Consultation & Clearance -->
                <div class="stage-interactive-card" data-cat="consultation">
                    <span class="stage-ribbon" style="background: #d97706;"><i class="fa-solid fa-handshake-angle"></i> Consultation</span>
                    <h4>Adviser Readiness &amp; Defense Clearance</h4>
                    <p>Mandatory 4-step consultation flow ensuring that students have addressed all adviser feedback prior to oral defense scheduling.</p>
                    <ul class="stage-feature-list">
                        <li><i class="fa-solid fa-check"></i> Formal Student Consultation Form</li>
                        <li><i class="fa-solid fa-check"></i> Pre-Acceptance Readiness Evaluation</li>
                        <li><i class="fa-solid fa-check"></i> One-Click Oral Defense Endorsement</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- ==========================================
         PLATFORM CAPABILITIES (6-Grid Feature Section)
         ========================================== -->
    <section class="section" id="capabilities">
        <div class="section-header">
            <span class="section-badge">Platform Capabilities</span>
            <h2 class="section-title">Built for Students, Advisers &amp; Panelists</h2>
            <p class="section-desc">Empowering Caraga State University with an interactive, end-to-end oral defense and capstone management platform.</p>
        </div>

        <div class="capabilities-grid">
            <!-- 1 -->
            <div class="capability-card">
                <div class="cap-icon-box">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <h3>AI &amp; MCDA Adviser Matching</h3>
                <p>Multi-Criteria Decision Analysis evaluating 5 weighted dimensions: research specialization, Google Scholar publications, advised titles track record, and workload capacity.</p>
                <a href="thesis_system/login.php">Explore matching engine <i class="fa-solid fa-arrow-right"></i></a>
            </div>

            <!-- 2 -->
            <div class="capability-card">
                <div class="cap-icon-box">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                <h3>Adviser Consultation Process</h3>
                <p>Structured consultation requests, follow-up Q&amp;A threads, and progress sign-offs ensuring students are fully prepared before oral defense approval.</p>
                <a href="thesis_system/login.php">View consultation flow <i class="fa-solid fa-arrow-right"></i></a>
            </div>

            <!-- 3 -->
            <div class="capability-card">
                <div class="cap-icon-box">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <h3>Defense Schedules Management</h3>
                <p>Dedicated calendars separating official administrator-scheduled oral defenses (Proposal Defense Title &amp; Final Defense) from 1-on-1 adviser meeting appointments.</p>
                <a href="thesis_system/login.php">Check defense calendar <i class="fa-solid fa-arrow-right"></i></a>
            </div>

            <!-- 4 -->
            <div class="capability-card">
                <div class="cap-icon-box">
                    <i class="fa-solid fa-microphone-lines"></i>
                </div>
                <h3>Voice Comments &amp; Transcripts</h3>
                <p>Examination panelists record direct voice feedback or type revision notes, instantly synchronized to candidate and adviser dashboards.</p>
                <a href="thesis_system/login.php">Examine panel tools <i class="fa-solid fa-arrow-right"></i></a>
            </div>

            <!-- 5 -->
            <div class="capability-card">
                <div class="cap-icon-box">
                    <i class="fa-solid fa-file-lines"></i>
                </div>
                <h3>Official Minutes of the Meeting</h3>
                <p>Defense Secretaries record live proceedings, attendance rosters, action items, and generate signed MOM PDFs for official university records.</p>
                <a href="thesis_system/login.php">Secretary portal <i class="fa-solid fa-arrow-right"></i></a>
            </div>

            <!-- 6 -->
            <div class="capability-card">
                <div class="cap-icon-box">
                    <i class="fa-solid fa-users-rectangle"></i>
                </div>
                <h3>Capstone Roster &amp; Requirements</h3>
                <p>Complete student team roster management with milestone checklists, document version tracking, and academic year archiving.</p>
                <a href="thesis_system/login.php">Student portal <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
    </section>

    <!-- ==========================================
         ORAL DEFENSE LIFECYCLE WORKFLOW
         ========================================== -->
    <section class="section section-light" id="workflow">
        <div class="section-header">
            <span class="section-badge">Academic Lifecycle</span>
            <h2 class="section-title">The Path to Capstone Oral Defense Success</h2>
            <p class="section-desc">A transparent, 4-phase milestone framework guiding research groups from concept to defense clearance.</p>
        </div>

        <div class="workflow-timeline">
            <!-- Step 1 -->
            <div class="workflow-node">
                <div class="workflow-step-badge">1</div>
                <h4>Topic &amp; Adviser Matching</h4>
                <p>Form capstone teams, draft initial research concepts, and utilize MCDA AI matching to pair with the best-fit faculty adviser.</p>
            </div>

            <!-- Step 2 -->
            <div class="workflow-node">
                <div class="workflow-step-badge">2</div>
                <h4>Consultation &amp; Readiness</h4>
                <p>Complete the required consultation process, answer adviser follow-up questions, and refine project scope and methodology.</p>
            </div>

            <!-- Step 3 -->
            <div class="workflow-node">
                <div class="workflow-step-badge">3</div>
                <h4>Proposal Defense Title</h4>
                <p>Submit proposal manuscripts, undergo panel deliberations, address revision notes, and secure formal title approval.</p>
            </div>

            <!-- Step 4 -->
            <div class="workflow-node">
                <div class="workflow-step-badge">4</div>
                <h4>Final Defense</h4>
                <p>Demonstrate working prototypes, present ISO 25010 evaluations, receive panel committee verdicts, and complete archiving.</p>
            </div>
        </div>
    </section>

    <!-- ==========================================
         FOOTER
         ========================================== -->
    <footer class="footer">
        <div class="footer-grid">
            <div>
                <div class="footer-brand">
                    <img src="thesis_system/logo.png" alt="CSU Logo">
                    <span>CSU CEIT</span>
                </div>
                <p class="footer-desc">
                    Caraga State University Cabadbaran Campus<br>
                    College of Engineering and Information Technology (CEIT)<br>
                    Interactive Platform for Capstone Project Oral Defense.
                </p>
            </div>

            <div class="footer-col">
                <h5>Navigation</h5>
                <ul class="footer-links">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#defense-hub">Oral Defense Hub</a></li>
                    <li><a href="#capabilities">Platform Features</a></li>
                    <li><a href="#workflow">Defense Lifecycle</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h5>Portals</h5>
                <ul class="footer-links">
                    <li><a href="thesis_system/login.php">Student Portal</a></li>
                    <li><a href="thesis_system/login.php">Faculty Adviser Portal</a></li>
                    <li><a href="thesis_system/login.php">Panelist Portal</a></li>
                    <li><a href="thesis_system/login.php">Defense Secretary / Admin</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Caraga State University - CEIT. All rights reserved.</p>
            <p>Interactive Platform for Capstone Project Oral Defense &bull; CSU Cabadbaran</p>
        </div>
    </footer>

    <!-- JavaScript Interactive Handlers -->
    <script>
        const toggleBtn = document.getElementById('mobileToggle');
        const drawer = document.getElementById('mobileDrawer');
        const backdrop = document.getElementById('mobileBackdrop');

        function toggleMobileMenu() {
            if (!drawer) return;
            const isOpen = drawer.classList.toggle('active');
            if (backdrop) backdrop.classList.toggle('active', isOpen);
            if (toggleBtn) {
                const icon = toggleBtn.querySelector('i');
                if (icon) {
                    icon.className = isOpen ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
                }
            }
            document.body.style.overflow = isOpen ? 'hidden' : '';
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleMobileMenu);
        }

        function closeMobileMenu() {
            if (drawer) drawer.classList.remove('active');
            if (backdrop) backdrop.classList.remove('active');
            if (toggleBtn) {
                const icon = toggleBtn.querySelector('i');
                if (icon) icon.className = 'fa-solid fa-bars';
            }
            document.body.style.overflow = '';
        }

        // Close mobile menu on resize to desktop
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                closeMobileMenu();
            }
        });

        // Live Hero Interactive Tab Switcher
        const heroTabData = {
            'it198': {
                stage: '<i class="fa-solid fa-file-signature"></i> Proposal Defense Title',
                title: '"IoT and AI-Driven Smart Agriculture Monitoring and Solar Optimization"',
                candidate: '<span><i class="fa-solid fa-users" style="color: #047857;"></i> Candidate: <strong>Group AgriTech (Leader: Vincent Jabla)</strong></span> &bull; CEIT Defense Hall',
                verdict: 'Approved w/ Minor Revisions',
                committee: 'Committee: Dr. Joseph A. Vistal (Chair) &bull; Engr. R. Gomez &bull; Prof. C. Villareal'
            },
            'it199': {
                stage: '<i class="fa-solid fa-award"></i> Final Defense',
                title: '"Machine Learning-Based Spam Message Detection & SMS Gateway System"',
                candidate: '<span><i class="fa-solid fa-users" style="color: #047857;"></i> Candidate: <strong>Group AI Guard (Leader: Jessrel James)</strong></span> &bull; Audio Deliberations Synchronized',
                verdict: 'Passed (Final Defense)',
                committee: 'Committee: Dr. Alexander Lim (Chair) &bull; Engr. J. Alcantara &bull; Dr. F. Santos'
            },
            'consultation': {
                stage: '<i class="fa-solid fa-clipboard-check"></i> Pre-Acceptance Consultation',
                title: '"Adviser Readiness Evaluation & Scope Refinement Session"',
                candidate: '<span><i class="fa-solid fa-user-graduate" style="color: #047857;"></i> Candidate: <strong>Rjay Enriquez</strong></span> &bull; CEIT Faculty Consultation Room 102',
                verdict: 'Ready for Proposal Defense',
                committee: 'Adviser: Prof. Jason Ah &bull; Consultation Form CEIT-CF-2026 Completed'
            }
        };

        function switchHeroTab(tabKey) {
            document.querySelectorAll('.def-tab-btn').forEach(b => b.classList.remove('active'));
            if (event && event.currentTarget) event.currentTarget.classList.add('active');

            const data = heroTabData[tabKey] || heroTabData['it198'];
            document.getElementById('heroStageBadge').innerHTML = data.stage;
            document.getElementById('heroTitleText').textContent = data.title;
            document.getElementById('heroCandidateText').innerHTML = data.candidate;
            document.getElementById('heroVerdictText').textContent = data.verdict;
            document.getElementById('heroCommitteeText').innerHTML = data.committee;
        }

        // Showcase Filter
        function filterShowcase(category) {
            document.querySelectorAll('.btn-showcase-filter').forEach(btn => btn.classList.remove('active'));
            if (event && event.currentTarget) event.currentTarget.classList.add('active');

            const cards = document.querySelectorAll('.stage-interactive-card');
            cards.forEach(card => {
                const cat = card.getAttribute('data-cat');
                if (category === 'all' || cat === category) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
