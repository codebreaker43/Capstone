-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: ceit_thesis
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `adviser_profile`
--

DROP TABLE IF EXISTS `adviser_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `adviser_profile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `expertise` text DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `adviser_profile_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `adviser_profile`
--

LOCK TABLES `adviser_profile` WRITE;
/*!40000 ALTER TABLE `adviser_profile` DISABLE KEYS */;
INSERT INTO `adviser_profile` VALUES (1,1,'Machine Learning, Artificial Intelligence, Natural Language Processing, Data Science, Python',5),(2,2,'Web Development, React, Node.js, Cloud Computing, Database Management, PHP',5),(3,3,'Internet of Things, IoT, Embedded Systems, Robotics, Hardware Design, C++',5),(4,7,'',5);
/*!40000 ALTER TABLE `adviser_profile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `match_request`
--

DROP TABLE IF EXISTS `match_request`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `match_request` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `adviser_id` int(11) NOT NULL,
  `match_score` float NOT NULL,
  `status` varchar(20) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `adviser_id` (`adviser_id`),
  CONSTRAINT `match_request_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `user` (`id`),
  CONSTRAINT `match_request_ibfk_2` FOREIGN KEY (`adviser_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `match_request`
--

LOCK TABLES `match_request` WRITE;
/*!40000 ALTER TABLE `match_request` DISABLE KEYS */;
INSERT INTO `match_request` VALUES (1,5,2,9.25,'pending');
/*!40000 ALTER TABLE `match_request` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_profile`
--

DROP TABLE IF EXISTS `student_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_profile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `skills` text DEFAULT NULL,
  `thesis_topic` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `student_profile_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_profile`
--

LOCK TABLES `student_profile` WRITE;
/*!40000 ALTER TABLE `student_profile` DISABLE KEYS */;
INSERT INTO `student_profile` VALUES (1,4,'programmer','Design and Development of a CEIT AI-Powered Thesis Adviser Matching System Using Skill-Based Recommendation for Students'),(2,5,'AI','Design and Development of a CEIT AI-Powered Thesis Adviser Matching System Using Skill-Based Recommendation for Students'),(3,6,'','');
/*!40000 ALTER TABLE `student_profile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firstname` varchar(50) DEFAULT NULL,
  `lastname` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `address` varchar(200) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(60) NOT NULL,
  `role` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (1,NULL,NULL,'Dr. Alan Turing','alan@ceit.edu',NULL,NULL,'$2b$12$B2348cmGxYhV1v8Gzq344uEpqBxY0woo3WxYM3Nw0jVa3SuWclW26','adviser'),(2,NULL,NULL,'Prof. Ada Lovelace','ada@ceit.edu',NULL,NULL,'$2b$12$RBZBI7bNephUx8/Y5AYhPeBxNWakI.0xd.0OT446I.mPWIyh0a/R6','adviser'),(3,NULL,NULL,'Engr. Nikola Tesla','nikola@ceit.edu',NULL,NULL,'$2b$12$AfmqqTeFi4trMX0IqcVxLuGVBSh8lW6bH7KmXbAvyH21LpBpyIr.q','adviser'),(4,NULL,NULL,'Jessrel James Galagate','jessreljames.galagate@gmail.com',NULL,NULL,'$2b$12$AfqRJUV2KqGqfmjYMhn8u..1uZ9zc1cYByLeOluUo2NzEDEV6xHti','student'),(5,NULL,NULL,'jessreljames.galagate','jess1@gmail.com',NULL,NULL,'$2y$10$4PLPUZHk95/5heAzSX4hWeRIy5/urVP2sl0T2PwHU8/1sueBNJ3R.','student'),(6,NULL,NULL,'jessreljames.galagate','jess2@gmail.com',NULL,NULL,'$2y$10$FVMwQeQfAaUjobI.69ynkewBGtwjey1jmc07pBhnH1eBk4n6dj5Ji','student'),(7,'JESSREL','GALAGATE','JESSREL GALAGATE','jessreljames.galagate@csucc.edu.ph','Purok 9 Balang Balanag RTR ADN','09876543211','$2y$10$cpOcdIJpjlBCgRNtODROG.67ug/s4D330/Oa/XIS9YHpXF5CD9Yj.','adviser'),(8,NULL,NULL,'System Admin','admin@ceit.edu',NULL,NULL,'$2y$10$dC0zPbBCtYu3XZ/cZRbPaeAfwoCDINH2UQtToEg7O0RQq8PS6d8n.','admin');
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-26 19:49:06
