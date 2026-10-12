-- =====================================================================
--  ORBITDESK - Online Jobs Portal for Virtual Assistants
--  ITELEC 4100 | Project #1 | Clarence Aron C. Gono | BSIT 4-1 LAB B
--  Import this file in phpMyAdmin BEFORE running the application.
-- =====================================================================

DROP DATABASE IF EXISTS `orbitdesk`;
CREATE DATABASE `orbitdesk` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `orbitdesk`;

-- ---------------------------------------------------------------------
-- TABLE 1: tbl_admins  (staff who verify client and VA profiles)
-- ---------------------------------------------------------------------
CREATE TABLE `tbl_admins` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `full_name`   VARCHAR(100) NOT NULL,
  `email`       VARCHAR(120) NOT NULL,
  `password`    VARCHAR(255) NOT NULL,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- TABLE 2: tbl_clients  (companies posting the work)
-- ---------------------------------------------------------------------
CREATE TABLE `tbl_clients` (
  `id`             INT(11) NOT NULL AUTO_INCREMENT,
  `company_name`   VARCHAR(120) NOT NULL,
  `contact_person` VARCHAR(100) NOT NULL,
  `email`          VARCHAR(120) NOT NULL,
  `password`       VARCHAR(255) NOT NULL,
  `phone`          VARCHAR(30)  NOT NULL,
  `location`       VARCHAR(120) NOT NULL,
  `status`         ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `verified_by`    INT(11) DEFAULT NULL,
  `verified_at`    DATETIME DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_client_email` (`email`),
  KEY `fk_client_admin` (`verified_by`),
  CONSTRAINT `fk_client_admin` FOREIGN KEY (`verified_by`)
    REFERENCES `tbl_admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- TABLE 3: tbl_vas  (virtual assistants looking for work)
-- ---------------------------------------------------------------------
CREATE TABLE `tbl_vas` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `full_name`        VARCHAR(100) NOT NULL,
  `email`            VARCHAR(120) NOT NULL,
  `password`         VARCHAR(255) NOT NULL,
  `phone`            VARCHAR(30)  NOT NULL,
  `skills`           VARCHAR(255) NOT NULL,
  `experience_years` INT(11) NOT NULL DEFAULT 0,
  `hourly_rate`      DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `availability`     ENUM('full-time','part-time','project-based') NOT NULL DEFAULT 'full-time',
  `status`           ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `verified_by`      INT(11) DEFAULT NULL,
  `verified_at`      DATETIME DEFAULT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_va_email` (`email`),
  KEY `fk_va_admin` (`verified_by`),
  CONSTRAINT `fk_va_admin` FOREIGN KEY (`verified_by`)
    REFERENCES `tbl_admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- TABLE 4: tbl_jobs  (one client posts many jobs)
-- ---------------------------------------------------------------------
CREATE TABLE `tbl_jobs` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `client_id`    INT(11) NOT NULL,
  `title`        VARCHAR(150) NOT NULL,
  `category`     VARCHAR(80)  NOT NULL,
  `description`  TEXT NOT NULL,
  `rate_offered` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `rate_type`    ENUM('hourly','monthly') NOT NULL DEFAULT 'hourly',
  `slots`        INT(11) NOT NULL DEFAULT 1,
  `status`       ENUM('open','closed','filled') NOT NULL DEFAULT 'open',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_job_client` (`client_id`),
  CONSTRAINT `fk_job_client` FOREIGN KEY (`client_id`)
    REFERENCES `tbl_clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- TABLE 5: tbl_applications  (junction table: many VAs <-> many jobs)
-- ---------------------------------------------------------------------
CREATE TABLE `tbl_applications` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `job_id`        INT(11) NOT NULL,
  `va_id`         INT(11) NOT NULL,
  `cover_note`    TEXT NOT NULL,
  `expected_rate` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `status`        ENUM('pending','shortlisted','hired','rejected') NOT NULL DEFAULT 'pending',
  `applied_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_job_va` (`job_id`,`va_id`),
  KEY `fk_app_va` (`va_id`),
  CONSTRAINT `fk_app_job` FOREIGN KEY (`job_id`)
    REFERENCES `tbl_jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_app_va` FOREIGN KEY (`va_id`)
    REFERENCES `tbl_vas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
--  SEED DATA
-- =====================================================================

-- Admins. Plain passwords: admin123 / mod123 / mod456 / mod789
INSERT INTO `tbl_admins` (`id`,`full_name`,`email`,`password`,`status`,`created_at`) VALUES
(1,'Clarence Aron C. Gono','admin@orbitdesk.ph','$2y$10$lMZAHa4dk3bG14TGE/AVCOFnD45dyWBMJqTWjy8KFiy8ADUfe3CJe','active','2026-06-01 08:15:00'),
(2,'Mariel S. Bautista','mariel@orbitdesk.ph','$2y$10$X78AGX0hVif0F58R4z29zOnk/WpsOr.F1HOH31y8I1C/v0VOfuEpq','active','2026-06-03 09:40:00'),
(3,'Dexter L. Villanueva','dexter@orbitdesk.ph','$2y$10$ssERWiAEitoZqHZ3Qb0KQ.hF4Spz/MnPvn8/bqnlhCVVm9TQj/7Z2','active','2026-06-05 13:20:00'),
(4,'Reina P. Alcantara','reina@orbitdesk.ph','$2y$10$yrY8jrSNYEAdDlsegwz6S.vMILbu4wdTMsstTCO69eka7MzykCECa','inactive','2026-06-11 16:05:00');

-- Clients
INSERT INTO `tbl_clients` (`id`,`company_name`,`contact_person`,`email`,`password`,`phone`,`location`,`status`,`verified_by`,`verified_at`,`created_at`) VALUES
(1,'Northgate Property Group','Alicia Reyes','alicia@northgateproperty.com','$2y$10$a88gYDtbnKtCu3KdOrwWsOWl7lrmq8QQAH7dlyqyUXFaoY0zZnu1S','+1 619 555 0142','San Diego, CA','verified',1,'2026-06-08 10:12:00','2026-06-06 07:30:00'),
(2,'Brightline Dental Studio','Marcus Webb','marcus@brightlinedental.com','$2y$10$a88gYDtbnKtCu3KdOrwWsOWl7lrmq8QQAH7dlyqyUXFaoY0zZnu1S','+1 512 555 0188','Austin, TX','verified',2,'2026-06-09 11:45:00','2026-06-07 08:10:00'),
(3,'Kaya Coffee Roasters','Nina Salazar','nina@kayaroasters.com','$2y$10$a88gYDtbnKtCu3KdOrwWsOWl7lrmq8QQAH7dlyqyUXFaoY0zZnu1S','+63 917 442 8811','Makati City, PH','verified',2,'2026-06-12 09:05:00','2026-06-10 14:22:00'),
(4,'Halcyon Fitness Co.','Trevor Nash','trevor@halcyonfit.com','$2y$10$a88gYDtbnKtCu3KdOrwWsOWl7lrmq8QQAH7dlyqyUXFaoY0zZnu1S','+1 305 555 0119','Miami, FL','verified',3,'2026-06-14 15:33:00','2026-06-13 10:48:00'),
(5,'Summit Ledger Accounting','Priya Raman','priya@summitledger.com','$2y$10$a88gYDtbnKtCu3KdOrwWsOWl7lrmq8QQAH7dlyqyUXFaoY0zZnu1S','+1 415 555 0173','San Francisco, CA','verified',1,'2026-06-16 08:52:00','2026-06-15 09:00:00'),
(6,'Driftwood Travel Agency','Owen Castillo','owen@driftwoodtravel.com','$2y$10$a88gYDtbnKtCu3KdOrwWsOWl7lrmq8QQAH7dlyqyUXFaoY0zZnu1S','+61 2 5550 3321','Sydney, AU','pending',NULL,NULL,'2026-06-18 12:14:00'),
(7,'Pixelcrate Studios','Hannah Lim','hannah@pixelcrate.io','$2y$10$a88gYDtbnKtCu3KdOrwWsOWl7lrmq8QQAH7dlyqyUXFaoY0zZnu1S','+65 6555 2210','Singapore','verified',3,'2026-06-20 10:27:00','2026-06-19 16:40:00'),
(8,'Vireo Health Supplements','Daniel Okafor','daniel@vireohealth.com','$2y$10$a88gYDtbnKtCu3KdOrwWsOWl7lrmq8QQAH7dlyqyUXFaoY0zZnu1S','+44 20 5550 8842','London, UK','pending',NULL,NULL,'2026-06-22 09:19:00'),
(9,'Cornerstone Legal Partners','Beatriz Domingo','beatriz@cornerstonelegal.ph','$2y$10$a88gYDtbnKtCu3KdOrwWsOWl7lrmq8QQAH7dlyqyUXFaoY0zZnu1S','+63 918 220 4477','Cebu City, PH','verified',1,'2026-06-24 14:06:00','2026-06-23 11:35:00'),
(10,'Loop & Ladder E-commerce','Sam Whitaker','sam@loopandladder.com','$2y$10$a88gYDtbnKtCu3KdOrwWsOWl7lrmq8QQAH7dlyqyUXFaoY0zZnu1S','+1 206 555 0164','Seattle, WA','rejected',3,'2026-06-26 17:41:00','2026-06-25 13:12:00');

-- Virtual assistants
INSERT INTO `tbl_vas` (`id`,`full_name`,`email`,`password`,`phone`,`skills`,`experience_years`,`hourly_rate`,`availability`,`status`,`verified_by`,`verified_at`,`created_at`) VALUES
(1,'Jasmine R. Ocampo','jasmine.ocampo@gmail.com','$2y$10$pNaNqxxJsfc3.ucTwiFVEuHkGtKgDrmMhAAp8WYjUbhLZ0Adjp2ZK','+63 917 331 2244','Calendar management, Email handling, CRM data entry',5,9.50,'full-time','verified',1,'2026-06-09 08:30:00','2026-06-07 06:55:00'),
(2,'Kier D. Manalo','kier.manalo@gmail.com','$2y$10$pNaNqxxJsfc3.ucTwiFVEuHkGtKgDrmMhAAp8WYjUbhLZ0Adjp2ZK','+63 926 118 7733','Bookkeeping, QuickBooks, Xero, Invoicing',4,11.00,'full-time','verified',2,'2026-06-10 09:14:00','2026-06-08 07:20:00'),
(3,'Althea M. Fernandez','althea.fernandez@gmail.com','$2y$10$pNaNqxxJsfc3.ucTwiFVEuHkGtKgDrmMhAAp8WYjUbhLZ0Adjp2ZK','+63 915 774 9021','Social media management, Canva, Content scheduling',3,8.00,'part-time','verified',2,'2026-06-11 10:02:00','2026-06-09 15:48:00'),
(4,'Renz P. Aguilar','renz.aguilar@gmail.com','$2y$10$pNaNqxxJsfc3.ucTwiFVEuHkGtKgDrmMhAAp8WYjUbhLZ0Adjp2ZK','+63 939 552 6610','Lead generation, Cold email, LinkedIn outreach',6,12.50,'full-time','verified',3,'2026-06-13 11:38:00','2026-06-11 08:05:00'),
(5,'Camille B. Soriano','camille.soriano@gmail.com','$2y$10$pNaNqxxJsfc3.ucTwiFVEuHkGtKgDrmMhAAp8WYjUbhLZ0Adjp2ZK','+63 977 210 3388','Customer service, Zendesk, Live chat support',2,7.00,'full-time','verified',1,'2026-06-15 13:25:00','2026-06-14 10:33:00'),
(6,'Miguel A. Tolentino','miguel.tolentino@gmail.com','$2y$10$pNaNqxxJsfc3.ucTwiFVEuHkGtKgDrmMhAAp8WYjUbhLZ0Adjp2ZK','+63 921 664 5512','Video editing, Premiere Pro, CapCut, Short-form reels',4,13.00,'project-based','verified',3,'2026-06-17 09:47:00','2026-06-16 12:19:00'),
(7,'Bianca L. Cruz','bianca.cruz@gmail.com','$2y$10$pNaNqxxJsfc3.ucTwiFVEuHkGtKgDrmMhAAp8WYjUbhLZ0Adjp2ZK','+63 906 883 1190','Content writing, SEO, Blog management',5,10.00,'part-time','verified',2,'2026-06-19 14:10:00','2026-06-18 09:41:00'),
(8,'Joshua V. Ramirez','joshua.ramirez@gmail.com','$2y$10$pNaNqxxJsfc3.ucTwiFVEuHkGtKgDrmMhAAp8WYjUbhLZ0Adjp2ZK','+63 998 447 2205','Graphic design, Figma, Adobe Illustrator, Branding',3,11.50,'project-based','pending',NULL,NULL,'2026-06-20 16:28:00'),
(9,'Trisha Mae G. Lopez','trisha.lopez@gmail.com','$2y$10$pNaNqxxJsfc3.ucTwiFVEuHkGtKgDrmMhAAp8WYjUbhLZ0Adjp2ZK','+63 945 119 6674','Real estate VA, MLS listing input, Transaction coordination',4,10.50,'full-time','verified',1,'2026-06-22 08:16:00','2026-06-21 11:02:00'),
(10,'Paolo E. Santos','paolo.santos@gmail.com','$2y$10$pNaNqxxJsfc3.ucTwiFVEuHkGtKgDrmMhAAp8WYjUbhLZ0Adjp2ZK','+63 933 720 8845','Data entry, Excel, Google Sheets, Web research',2,6.50,'part-time','pending',NULL,NULL,'2026-06-23 13:55:00'),
(11,'Nadine F. Delgado','nadine.delgado@gmail.com','$2y$10$pNaNqxxJsfc3.ucTwiFVEuHkGtKgDrmMhAAp8WYjUbhLZ0Adjp2ZK','+63 927 505 3319','Executive assistance, Travel booking, Minutes taking',7,14.00,'full-time','verified',3,'2026-06-25 10:44:00','2026-06-24 07:38:00'),
(12,'Emman K. Bautista','emman.bautista@gmail.com','$2y$10$pNaNqxxJsfc3.ucTwiFVEuHkGtKgDrmMhAAp8WYjUbhLZ0Adjp2ZK','+63 919 336 7702','E-commerce support, Shopify, Order fulfillment',3,9.00,'full-time','verified',2,'2026-06-27 15:21:00','2026-06-26 09:26:00'),
(13,'Sofia R. Mendoza','sofia.mendoza@gmail.com','$2y$10$pNaNqxxJsfc3.ucTwiFVEuHkGtKgDrmMhAAp8WYjUbhLZ0Adjp2ZK','+63 947 228 4460','Medical VA, Patient scheduling, Insurance verification',5,12.00,'full-time','pending',NULL,NULL,'2026-06-28 08:49:00'),
(14,'Gabriel T. Navarro','gabriel.navarro@gmail.com','$2y$10$pNaNqxxJsfc3.ucTwiFVEuHkGtKgDrmMhAAp8WYjUbhLZ0Adjp2ZK','+63 908 611 9934','Podcast editing, Audacity, Show notes',1,5.50,'project-based','rejected',3,'2026-06-30 12:07:00','2026-06-29 14:15:00');

-- Jobs
INSERT INTO `tbl_jobs` (`id`,`client_id`,`title`,`category`,`description`,`rate_offered`,`rate_type`,`slots`,`status`,`created_at`) VALUES
(1,1,'Real Estate Transaction Coordinator','Real Estate VA','Handle listing paperwork, coordinate escrow timelines, and keep the CRM updated for a busy coastal brokerage.',11.00,'hourly',2,'open','2026-06-09 08:00:00'),
(2,1,'Listing Data Entry Specialist','Data Entry','Encode property details, upload photo sets, and proofread listing remarks before they go live.',7.50,'hourly',1,'open','2026-06-12 09:30:00'),
(3,2,'Patient Scheduling Assistant','Customer Service','Answer inbound calls, book appointments, and send reminder texts for a three-chair dental practice.',8.50,'hourly',2,'open','2026-06-11 10:15:00'),
(4,2,'Dental Insurance Verifier','Admin Support','Verify coverage with providers, log benefits in the practice software, and flag lapsed policies.',9.00,'hourly',1,'filled','2026-06-13 11:05:00'),
(5,3,'Social Media Manager for Coffee Brand','Social Media Management','Plan a monthly content calendar, edit reels, and reply to comments across Instagram and TikTok.',8.00,'hourly',1,'open','2026-06-14 13:40:00'),
(6,3,'Shopify Store Assistant','E-commerce Support','Process orders, update product pages, and handle shipping follow-ups for a growing roastery.',600.00,'monthly',1,'open','2026-06-17 08:22:00'),
(7,4,'Fitness Content Video Editor','Video Editing','Cut long-form gym footage into short vertical clips with captions and beat-matched transitions.',13.50,'hourly',2,'open','2026-06-16 15:12:00'),
(8,4,'Membership Support Representative','Customer Service','Answer member questions, process pauses and cancellations, and log every ticket in Zendesk.',7.00,'hourly',3,'open','2026-06-18 09:55:00'),
(9,5,'Bookkeeping Assistant (Xero)','Bookkeeping','Reconcile bank feeds, categorize expenses, and prepare monthly reports for small business clients.',11.50,'hourly',2,'open','2026-06-17 14:28:00'),
(10,5,'Accounts Receivable Follow-up','Bookkeeping','Chase overdue invoices politely, update aging reports, and escalate accounts past sixty days.',10.00,'hourly',1,'closed','2026-06-20 10:06:00'),
(11,7,'Brand Identity Designer','Graphic Design','Build logo suites, social templates, and pitch decks for early-stage startup clients.',12.00,'hourly',1,'open','2026-06-21 11:33:00'),
(12,7,'UI Asset Production Assistant','Graphic Design','Resize and export design assets from Figma into the delivery folders used by the dev team.',8.50,'hourly',2,'open','2026-06-23 16:44:00'),
(13,9,'Legal Administrative Assistant','Admin Support','Draft correspondence, organize case files, and track filing deadlines for a litigation team.',10.50,'hourly',1,'open','2026-06-25 08:37:00'),
(14,9,'Client Intake Coordinator','Customer Service','Screen inquiries, run conflict checks, and schedule consultations for incoming legal matters.',9.50,'hourly',1,'open','2026-06-26 13:18:00'),
(15,1,'Lead Generation Specialist','Lead Generation','Build targeted prospect lists, run cold email sequences, and book qualified seller appointments.',12.50,'hourly',2,'open','2026-06-27 09:12:00'),
(16,5,'Executive Assistant to the Managing Partner','Executive Assistant','Own the calendar, prep briefing notes, and coordinate travel across three time zones.',14.00,'hourly',1,'open','2026-06-29 07:50:00');

-- Applications (junction table)
INSERT INTO `tbl_applications` (`id`,`job_id`,`va_id`,`cover_note`,`expected_rate`,`status`,`applied_at`) VALUES
(1,1,9,'Four years coordinating escrow files for two San Diego brokerages. I know MLS input and can start Monday.',11.00,'hired','2026-06-10 09:15:00'),
(2,1,1,'Five years of calendar and CRM work. Happy to learn the transaction side, I pick up systems fast.',9.50,'shortlisted','2026-06-11 10:40:00'),
(3,2,10,'Fast and accurate encoder, roughly 65 wpm with clean proofreading habits.',6.50,'pending','2026-06-13 08:28:00'),
(4,2,9,'I already do listing input daily so this would be an easy fit alongside my current load.',8.00,'shortlisted','2026-06-14 11:52:00'),
(5,3,5,'Two years on live chat and phones for a healthcare BPO. Comfortable with HIPAA basics.',7.00,'hired','2026-06-12 14:07:00'),
(6,3,13,'Medical VA background with patient scheduling and insurance verification experience.',12.00,'pending','2026-06-15 09:33:00'),
(7,4,13,'I verify benefits daily for a US clinic and can handle a full provider list.',12.00,'hired','2026-06-14 15:20:00'),
(8,5,3,'I run content calendars for two food brands. Reels and Canva templates are my daily work.',8.00,'hired','2026-06-15 10:12:00'),
(9,5,7,'Writer first, but I schedule and caption for a lifestyle account with 40k followers.',10.00,'rejected','2026-06-16 13:45:00'),
(10,6,12,'Shopify order processing and fulfillment for a US apparel store, three years running.',9.00,'shortlisted','2026-06-18 09:04:00'),
(11,6,10,'Strong on spreadsheets and order tracking. Willing to train on Shopify admin.',6.50,'pending','2026-06-19 16:22:00'),
(12,7,6,'Short-form editing is my main service. I average twelve reels a week per client.',13.00,'hired','2026-06-17 17:36:00'),
(13,7,14,'Mostly audio work so far, but I edit gym clips for a local trainer on the side.',5.50,'rejected','2026-06-19 08:41:00'),
(14,8,5,'Zendesk certified and used to handling forty tickets a day without dropping quality.',7.00,'shortlisted','2026-06-19 11:15:00'),
(15,8,1,'I can cover overflow tickets alongside admin support, part-time to start.',9.50,'pending','2026-06-20 14:50:00'),
(16,8,12,'Order support translates well to membership questions. Same tools, same tone.',9.00,'pending','2026-06-21 09:27:00'),
(17,9,2,'Xero and QuickBooks daily for four years across six small business clients.',11.00,'hired','2026-06-18 08:19:00'),
(18,10,2,'Collections follow-up is part of my current role, tone stays firm but friendly.',11.00,'shortlisted','2026-06-21 13:02:00'),
(19,11,8,'Figma-first designer with six brand identity projects in the last year.',11.50,'pending','2026-06-22 10:38:00'),
(20,12,8,'Asset export and resizing is routine for me, I can turn around batches same day.',11.50,'pending','2026-06-24 15:11:00'),
(21,13,11,'Seven years supporting executives, including two at a Cebu law office.',14.00,'shortlisted','2026-06-26 09:44:00'),
(22,14,5,'Intake screening is close to my support work, and I am comfortable on the phone.',7.50,'pending','2026-06-27 11:06:00'),
(23,15,4,'Built prospect lists and ran cold sequences that booked 30 plus meetings a quarter.',12.50,'hired','2026-06-28 08:53:00'),
(24,15,1,'I handle CRM hygiene and follow-up sequences, happy to support the outreach side.',9.50,'pending','2026-06-29 10:29:00'),
(25,16,11,'Calendar ownership across three time zones is exactly what I do now.',14.00,'shortlisted','2026-06-30 07:47:00'),
(26,16,1,'Five years as a general VA, ready to step into a full executive assistant role.',10.00,'pending','2026-07-01 09:18:00');
