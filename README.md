Secure Internal Corporate Messaging System

UTHM Bursary Office — Final Year Project | Session 2025/2026

OVERVIEW

A production-grade, end-to-end encrypted internal messaging platform designed for the UTHM Bursary Office. Built to address the security gaps in conventional messaging tools used in corporate environments, this system ensures that all communications remain private, authenticated, and tamper-proof even in the event of server compromise.

FEATURES

SECURITY


End-to-End Encryption — Signal Protocol implementation (X3DH key exchange + Double Ratchet Algorithm) for personal chats
Group Encryption — Sender Key Protocol for scalable group message encryption
Encryption at Rest — AES-256-GCM for all stored messages and files
Password Hashing — bcrypt with PBKDF2 key derivation
CSRF Protection — Token-based request validation on all state-changing endpoints
Account Recovery — Shamir's Secret Sharing (3-of-5 threshold scheme) for cryptographic key recovery without a single point of failure
OTP Recovery — Time-limited one-time password via email for account access
Audit Logging — Full activity trail for administrative oversight


MESSAGING


Real-time message delivery via Server-Sent Events (SSE)
Personal (1-to-1) and group chat support
Encrypted file sharing with random hex blob storage
Message read receipts
Session-based device management and termination


ADMINISTRATION


Role-based access control (Admin / Staff)
User management dashboard
Audit log viewer
System health monitoring



TECH STACK

LayerTechnologyBackendPHP 8.xFrontendVanilla JavaScript (ES6+), Bootstrap 5.3DatabaseMySQL 8.0CryptographyWeb Crypto API, Signal Protocol (custom implementation)Real-timeServer-Sent Events (SSE)EmailPHPMailer + Gmail SMTPServerApache on Ubuntu 24.04 LTS (Hostinger KVM VPS)Version ControlGit / GitHub


SYSTEM ARCHITECTURE

├── admin/              # Admin panel (user management, audit logs)
├── api/                # Backend API endpoints (messaging, auth, SSE)
├── assets/             # Static assets (CSS, JS, images)
├── config/             # Configuration files (DB connection — gitignored)
├── includes/           # Shared PHP components (session, helpers)
├── staff/              # Staff-facing UI (chat, dashboard)
├── uploads/encrypted/  # AES-256-GCM encrypted file blobs
├── vendor/phpmailer/   # PHPMailer library
├── index.php           # Entry point / login
├── recover.php         # Account recovery flow
└── setup_signal.php    # Signal Protocol key initialisation


CRYPTOGRAPHIC DESIGN

Personal Chat — Double Ratchet Algorithm

Each conversation uses an independent ratchet chain. Every message is encrypted with a unique message key derived from the ratchet state, providing forward secrecy and break-in recovery.

Key Exchange — X3DH (Extended Triple Diffie-Hellman)

Initial key establishment uses X3DH with identity keys, signed prekeys, and one-time prekeys — the same protocol used by Signal and WhatsApp.

Group Chat — Sender Key Protocol

Each group member holds a Sender Key. Messages are encrypted once per sender and decryptable by all authorised members, providing efficiency without sacrificing security.

Key Recovery — Shamir's Secret Sharing

The user's master key is split into 5 shares using a (3-of-5) threshold scheme. Any 3 shares are sufficient to reconstruct the key, eliminating single points of failure in account recovery.


Live Demo

🌐 uthm-messaging-system.online
Deployed on a Hostinger KVM VPS (Ubuntu 24.04, Apache, MySQL 8.0).
