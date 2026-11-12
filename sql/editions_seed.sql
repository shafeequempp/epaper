-- Mathrubhumi editions (generated from the edition list). Safe to run again: updates existing rows by slug.
SET NAMES utf8mb4;

INSERT INTO editions (slug, code, name, full_name, group_name, group_sort, legacy_id, sort_order, is_active, is_supplement, is_main) VALUES
  ('alappuzha', 'AT', 'Alappuzha', 'Alappuzha Town', 'Alappuzha', 100, 2, 101, 1, 0, 1),
  ('kuttanad', 'AK', 'Kuttanad', 'Kuttanad', 'Alappuzha', 100, 7, 102, 1, 0, 0),
  ('cherthala', 'AC', 'Cherthala', 'Cherthala', 'Alappuzha', 100, 5, 103, 1, 0, 0),
  ('aroor', 'AL', 'Aroor', 'Poochakkal', 'Alappuzha', 100, 3, 104, 1, 0, 0),
  ('kayamkulam', 'AH', 'Kayamkulam', 'Harippad', 'Alappuzha', 100, 12, 105, 1, 0, 0),
  ('mavelikkara', 'AM', 'Mavelikkara', 'Mavelikkara', 'Alappuzha', 100, 4, 106, 1, 0, 0),
  ('chengannur', 'AR', 'Chengannur', 'Chengannur', 'Alappuzha', 100, NULL, 107, 1, 0, 0),
  ('alappuzha-weekend', 'WKA', 'Alappuzha Weekend', 'Weekend Supplement', 'Alappuzha', 100, NULL, 110, 1, 1, 0),
  ('alappuzha-nagaram', 'NGA', 'Alappuzha Nagaram', 'Nagaram Supplement', 'Alappuzha', 100, NULL, 111, 1, 1, 0),
  ('alappuzha-chithrabhumi', 'NGC', 'Alappuzha Chithrabhumi', 'Chithrabhumi Supplement', 'Alappuzha', 100, NULL, 112, 1, 1, 0),
  ('alappuzha-second-daily', 'SDA', 'Alappuzha Second Daily', 'Second Daily', 'Alappuzha', 100, NULL, 113, 1, 1, 0),
  ('ernakulam', 'CC', 'Ernakulam', 'Cochin City', 'Ernakulam', 200, 9, 201, 1, 0, 1),
  ('tripunithura-mulanthuruthy', 'TM', 'Tripunithura-Mulanthuruthy', 'Tripunithura', 'Ernakulam', 200, 14, 202, 1, 0, 0),
  ('aroor-mattanchery', 'ET', 'Aroor-Mattanchery', 'Kochi - Alappuzha Combined', 'Ernakulam', 200, 11, 203, 1, 0, 0),
  ('paravur-vypin', 'EC', 'Paravur-Vypin', 'Paravur, Cherai', 'Ernakulam', 200, 15, 204, 1, 0, 0),
  ('aluva-angamaly-kalady', 'EA', 'Aluva-Angamaly-Kalady', 'Aluva, Angamaly, Kalady', 'Ernakulam', 200, 8, 205, 1, 0, 0),
  ('perumbavoor-kolenchery', 'EP', 'Perumbavoor-Kolenchery', 'Perumbavoor, Kolenchery', 'Ernakulam', 200, 16, 206, 1, 0, 0),
  ('kothamangalam-muvattupuzha', 'E', 'Kothamangalam-Muvattupuzha', 'Muvattupuzha, Kothamangalam, Koothattukulam, Piravom', 'Ernakulam', 200, 13, 207, 1, 0, 0),
  ('ernakulam-weekend', 'WKC', 'Ernakulam Weekend', 'Weekend Supplement', 'Ernakulam', 200, NULL, 208, 1, 1, 0),
  ('ernakulam-nagaram', 'NAC', 'Ernakulam Nagaram', 'Nagaram Supplement', 'Ernakulam', 200, NULL, 209, 1, 1, 0),
  ('ernakulam-chithrabhumi', 'CBC', 'Ernakulam Chithrabhumi', 'Chithrabhumi Supplement', 'Ernakulam', 200, NULL, 210, 1, 1, 0),
  ('ernakulam-second-daily', 'SDE', 'Ernakulam Second Daily', 'Second Daily', 'Ernakulam', 200, NULL, 213, 1, 1, 0),
  ('idukky', 'KYL', 'Idukky', 'Idukky Lowrange', 'Idukky', 300, 17, 301, 1, 0, 1),
  ('highrange', 'KYH', 'Highrange', 'Idukky Highrange', 'Idukky', 300, 18, 302, 1, 0, 0),
  ('kottayam-common', 'KY', 'Kottayam Common', 'Kottayam Common', 'Idukky', 300, 89, 350, 0, 0, 0),
  ('idukky-weekend', 'WKY', 'Idukky Weekend', 'Weekend Supplement', 'Idukky', 300, NULL, 309, 1, 1, 0),
  ('idukky-second-daily', 'SDY', 'Idukky Second Daily', 'Second Daily', 'Idukky', 300, NULL, 313, 1, 1, 0),
  ('pathanamthitta', 'PA', 'Pathanamthitta', 'Pathanamthitta', 'Pathanamthitta', 400, 58, 401, 1, 0, 1),
  ('adoor-panthalam', 'AP', 'Adoor, Panthalam', 'Adoor, Panthalam', 'Pathanamthitta', 400, NULL, 402, 1, 0, 0),
  ('kozhanchery-ranni', 'KR', 'Kozhanchery, Ranni', 'Kozhanchery, Ranni', 'Pathanamthitta', 400, NULL, 403, 1, 0, 0),
  ('thiruvalla', 'TK', 'Thiruvalla', 'Thiruvalla', 'Pathanamthitta', 400, 59, 404, 1, 0, 0),
  ('pathanamthitta-weekend', 'WKP', 'Pathanamthitta Weekend', 'Weekend Supplement', 'Pathanamthitta', 400, NULL, 405, 1, 1, 0),
  ('pathanamthitta-second-daily', 'SDP', 'Pathanamthitta Second Daily', 'Second Daily', 'Pathanamthitta', 400, NULL, 413, 1, 1, 0),
  ('kottayam', 'KK', 'Kottayam', 'Kottayam Town', 'Kottayam', 500, 33, 501, 1, 0, 1),
  ('changanacherry', 'PK', 'Changanacherry', 'Changanacherry', 'Kottayam', 500, 31, 502, 1, 0, 0),
  ('vaikom', 'VK', 'Vaikom', 'Vaikom', 'Kottayam', 500, 36, 503, 1, 0, 0),
  ('pala-erattupetta', 'PE', 'Pala, Erattupetta', 'Pala, Erattupetta', 'Kottayam', 500, 34, 504, 1, 0, 0),
  ('kottayam-weekend', 'WKK', 'Kottayam Weekend', 'Weekend Supplement', 'Kottayam', 500, NULL, 509, 1, 1, 0),
  ('kottayam-nagaram', 'NGY', 'Kottayam Nagaram', 'Nagaram Supplement', 'Kottayam', 500, NULL, 510, 1, 1, 0),
  ('kottayam-chithrabhumi', 'CBY', 'Kottayam Chithrabhumi', 'Chithrabhumi Supplement', 'Kottayam', 500, NULL, 511, 1, 1, 0),
  ('kottayam-second-daily', 'SDK', 'Kottayam Second Daily', 'Second Daily', 'Kottayam', 500, NULL, 513, 1, 1, 0),
  ('kannur', 'KT2', 'Kannur', 'Kannur Town', 'Kannur', 600, 6, 601, 1, 0, 1),
  ('thalassery', 'KT1', 'Thalassery', 'Thalassery', 'Kannur', 600, 23, 602, 1, 0, 0),
  ('thaliparamba', 'KR2', 'Thaliparamba', 'Thaliparamba', 'Kannur', 600, 24, 603, 1, 0, 0),
  ('payyanur', 'KR1', 'Payyanur', 'Payyanur', 'Kannur', 600, 22, 604, 1, 0, 0),
  ('iritty', 'KH', 'Iritty', 'Iritty', 'Kannur', 600, 19, 605, 1, 0, 0),
  ('kannur-kazhcha', 'KNG', 'Kannur Kazhcha', 'Kannur Kazhcha', 'Kannur', 600, 20, 606, 1, 1, 0),
  ('kannur-nagaram', 'NAK', 'Kannur Nagaram', 'Nagaram', 'Kannur', 600, NULL, 607, 1, 1, 0),
  ('kannur-weekend', 'WKN', 'Kannur Weekend', 'Weekend Supplement', 'Kannur', 600, NULL, 609, 1, 1, 0),
  ('kannur-second-daily', 'SDN', 'Kannur Second Daily', 'Second Daily', 'Kannur', 600, NULL, 613, 1, 1, 0),
  ('kasaragod', 'KG1', 'Kasaragod', 'Kasaragod', 'Kasaragod', 700, 96, 701, 1, 0, 1),
  ('kanhangad', 'KG2', 'Kanhangad', 'Kanhangad', 'Kasaragod', 700, 25, 702, 1, 0, 0),
  ('kasaragod-weekend', 'WKS', 'Kasaragod Weekend', 'Weekend Supplement', 'Kasaragod', 700, NULL, 709, 1, 1, 0),
  ('kasaragod-kazhcha', 'KAZ', 'Kasaragod Kazhcha', 'Kannur Kazhcha', 'Kasaragod', 700, NULL, 703, 1, 1, 0),
  ('kasaragod-second-daily', 'SDG', 'Kasaragod Second Daily', 'Second Daily', 'Kasaragod', 700, NULL, 713, 1, 1, 0),
  ('kollam', 'QT', 'Kollam', 'Kollam Town', 'Kollam', 800, 78, 801, 1, 0, 1),
  ('chathanoor', 'QC', 'Chathanoor', 'Chathanoor', 'Kollam', 800, 26, 802, 1, 0, 0),
  ('sastham-kotta', 'QS', 'Sastham Kotta', 'Sastham Kotta', 'Kollam', 800, 30, 803, 1, 0, 0),
  ('kottarakkara', 'QK', 'Kottarakkara', 'Kottarakkara', 'Kollam', 800, 28, 804, 1, 0, 0),
  ('punalur', 'QP', 'Punalur', 'Punalur', 'Kollam', 800, 29, 805, 1, 0, 0),
  ('kollam-nagaram', 'QNG', 'Kollam Nagaram', 'Nagaram Supplement', 'Kollam', 800, NULL, 806, 1, 1, 0),
  ('kollam-weekend', 'QVAR', 'Kollam Weekend', 'Weekend Supplement', 'Kollam', 800, NULL, 807, 1, 1, 0),
  ('kollam-second-daily', 'SDQ', 'Kollam Second Daily', 'Second Daily', 'Kollam', 800, NULL, 813, 1, 1, 0),
  ('kollam-micro', 'QSUP', 'Kollam Micro', 'Micro Supplement', 'Kollam', 800, NULL, 808, 1, 1, 0),
  ('malappuram', 'MT', 'Malappuram', 'Malappuram Town', 'Malappuram', 900, 46, 901, 1, 0, 1),
  ('valanchery', 'MV', 'Valanchery', 'Valanchery', 'Malappuram', 900, 50, 902, 1, 0, 0),
  ('tirur', 'MR', 'Tirur', 'Tirur', 'Malappuram', 900, 139, 903, 1, 0, 0),
  ('ponnani', 'MP', 'Ponnani', 'Ponnani', 'Malappuram', 900, 48, 904, 1, 0, 0),
  ('kondotty', 'MK', 'Kondotty', 'Kondotty', 'Malappuram', 900, 44, 905, 1, 0, 0),
  ('nilambur', 'MN', 'Nilambur', 'Nilambur', 'Malappuram', 900, 47, 906, 1, 0, 0),
  ('malappuram-weekend', 'WKN', 'Malappuram Weekend', 'Weekend Supplement', 'Malappuram', 900, 104, 907, 1, 1, 0),
  ('malappuram-second-daily', 'SDM', 'Malappuram Second Daily', 'Second Daily', 'Malappuram', 900, 127, 908, 1, 1, 0),
  ('palakkad', 'PT', 'Palakkad', 'Palakkad Town', 'Palakkad', 1000, 55, 1001, 1, 0, 1),
  ('chittur', 'PR', 'Chittur', 'Chittur', 'Palakkad', 1000, NULL, 1002, 1, 0, 0),
  ('vadakkanchery', 'PV', 'Vadakkanchery', 'Vadakkanchery', 'Palakkad', 1000, 57, 1003, 1, 0, 0),
  ('mannarkkad', 'PM', 'Mannarkkad', 'Mannarkkad', 'Palakkad', 1000, 52, 1004, 1, 0, 0),
  ('ottapalam', 'PO', 'Ottapalam', 'Ottapalam', 'Palakkad', 1000, 53, 1005, 1, 0, 0),
  ('pattambi', 'PP', 'Pattambi', 'Pattambi', 'Palakkad', 1000, 56, 1006, 1, 0, 0),
  ('coimbatore', 'OK', 'Coimbatore', 'Coimbatore', 'Palakkad', 1000, 51, 1007, 1, 0, 0),
  ('palakkad-weekend', 'WPK', 'Palakkad Weekend', 'Weekend Supplement', 'Palakkad', 1000, NULL, 1008, 1, 1, 0),
  ('palakkad-second-daily', 'SDL', 'Palakkad Second Daily', 'Second Daily', 'Palakkad', 1000, NULL, 1013, 1, 1, 0),
  ('palakkad-supplement', 'MPK', 'Palakkad Supplement', 'Micro Supplement', 'Palakkad', 1000, NULL, 1008, 1, 1, 0),
  ('trivandrum', 'TT', 'Trivandrum', 'Trivandrum Town', 'Trivandrum', 1100, 64, 1101, 1, 0, 1),
  ('nedumangad', 'TN', 'Nedumangad', 'Nedumangad', 'Trivandrum', 1100, 61, 1102, 1, 0, 0),
  ('attingal', 'TA', 'Attingal', 'Attingal', 'Trivandrum', 1100, 60, 1103, 1, 0, 0),
  ('neyyattinkara', 'TY', 'Neyyattinkara', 'Neyyattinkara', 'Trivandrum', 1100, 62, 1104, 1, 0, 0),
  ('trivandrum-weekend', 'WKT', 'Trivandrum Weekend', 'Weekend Supplement', 'Trivandrum', 1100, NULL, 1105, 1, 1, 0),
  ('trivandrum-nagaram', 'NAT', 'Trivandrum Nagaram', 'Nagaram Supplement', 'Trivandrum', 1100, NULL, 1106, 1, 1, 0),
  ('trivandrum-chithrabhumi', 'CBT', 'Trivandrum Chithrabhumi', 'Chithrabhumi Supplement', 'Trivandrum', 1100, NULL, 1107, 1, 1, 0),
  ('trivandrum-second-daily', 'SDT', 'Trivandrum Second Daily', 'Second Daily', 'Trivandrum', 1100, NULL, 1113, 1, 1, 0),
  ('thrissur', 'RT', 'Thrissur', 'Thrissur Town', 'Thrissur', 1200, 69, 1201, 1, 0, 1),
  ('chalakkudy', 'RX', 'Chalakkudy', 'Chalakkudy', 'Thrissur', 1200, 65, 1204, 1, 0, 0),
  ('guruvayur', 'RG', 'Guruvayur', 'Guruvayur', 'Thrissur', 1200, 66, 1202, 1, 0, 0),
  ('kodungallur', 'RK', 'Kodungallur', 'Kodungallur', 'Thrissur', 1200, 67, 1205, 1, 0, 0),
  ('wadakkanchery', 'RV', 'Wadakkanchery', 'Wadakkanchery', 'Thrissur', 1200, 68, 1203, 1, 0, 0),
  ('thrissur-nagaram', 'NR', 'Thrissur Nagaram', 'Nagaram Suppliment', 'Thrissur', 1200, NULL, 1206, 1, 1, 0),
  ('thrissur-weekend', 'WK', 'Thrissur Weekend', 'Weekend Suppliment', 'Thrissur', 1200, NULL, 1207, 1, 1, 0),
  ('thrissur-chithrabhumi', 'CBR', 'Thrissur Chithrabhumi', 'Chithrabhumi Suppliment', 'Thrissur', 1200, NULL, 1208, 1, 1, 0),
  ('thrissur-second-daily', 'SDR', 'Thrissur Second Daily', 'Second Daily', 'Thrissur', 1200, NULL, 1213, 1, 1, 0),
  ('thrissur-supplement', 'MK', 'Thrissur Supplement', 'Micro Supplement', 'Thrissur', 1200, NULL, 1209, 1, 1, 0),
  ('kozhikode', 'WT', 'Kozhikode', 'Kozhikode Town', 'Kozhikode', 1300, 39, 1301, 1, 0, 1),
  ('ramanattukara', 'WR', 'Ramanattukara', 'Ramanattukara', 'Kozhikode', 1300, 38, 1302, 1, 0, 0),
  ('thamarassery', 'KD', 'Thamarassery', 'Thamarassery', 'Kozhikode', 1300, 42, 1303, 1, 0, 0),
  ('koyilandy', 'KQ', 'Koyilandy', 'Koyilandy', 'Kozhikode', 1300, 40, 1304, 1, 0, 0),
  ('vadakara', 'KV', 'Vadakara', 'Vadakara', 'Kozhikode', 1300, 49, 1305, 1, 0, 0),
  ('kozhikode-weekend', 'VAR', 'Kozhikode Weekend', 'Weekend Suppliment', 'Kozhikode', 1300, NULL, 1306, 1, 1, 0),
  ('kozhikode-nagaram', 'NG', 'Kozhikode Nagaram', 'Nagaram Suppliment', 'Kozhikode', 1300, NULL, 1307, 1, 1, 0),
  ('kozhikode-chithrabhumi', 'CB', 'Kozhikode Chithrabhumi', 'Chithrabhumi Suppliment', 'Kozhikode', 1300, NULL, 1308, 1, 1, 0),
  ('kozhikode-second-daily', 'SDC', 'Kozhikode Second Daily', 'Second Daily', 'Kozhikode', 1300, NULL, 1313, 1, 1, 0),
  ('kozhikode-suppliment', 'MSC', 'Kozhikode Suppliment', 'Suppliment', 'Kozhikode', 1300, NULL, 1320, 1, 1, 0),
  ('wayanad', 'WD', 'Wayanad', 'Wayanad', 'Wayanad', 1400, 71, 1401, 1, 0, 1),
  ('wayanad-weekend', 'WVR', 'Wayanad Weekend', 'Weekend Suppliment', 'Wayanad', 1400, NULL, 1402, 1, 1, 0),
  ('wayanad-second-daily', 'SDW', 'Wayanad Second Daily', 'Second Daily', 'Wayanad', 1400, NULL, 1413, 1, 1, 0),
  ('wayanad-suppliment', 'MSW', 'Wayanad Suppliment', 'Suppliment', 'Wayanad', 1400, NULL, 1420, 1, 1, 0),
  ('bangalore', 'BL', 'Bangalore', 'Bangalore', 'Bangalore', 1500, 72, 1501, 1, 0, 1),
  ('bangalore-weekend', 'WBL', 'Bangalore Weekend', 'Weekend Suppliment', 'Bangalore', 1500, NULL, 1502, 1, 1, 0),
  ('chennai', 'CN', 'Chennai', 'Chennai', 'Chennai', 1600, 73, 1601, 1, 0, 1),
  ('chennai-weekend', 'WCN', 'Chennai Weekend', 'Weekend Suppliment', 'Chennai', 1600, NULL, 1602, 1, 1, 0),
  ('gulf', 'GF', 'Gulf', 'Uae', 'Gulf', 1700, 74, 1701, 1, 0, 1),
  ('gulf-weekend', 'WGF', 'Gulf Weekend', 'Weekend Suppliment', 'Gulf', 1700, NULL, 1702, 1, 1, 0),
  ('mumbai', 'BY', 'Mumbai', 'Bombay', 'Mumbai', 1800, 75, 1801, 1, 0, 1),
  ('mumbai-weekend', 'WBY', 'Mumbai Weekend', 'Weekend Suppliment', 'Mumbai', 1800, NULL, 1802, 1, 1, 0),
  ('new-delhi', 'ND', 'New Delhi', 'New Delhi', 'New Delhi', 1900, 76, 1901, 1, 0, 1),
  ('new-delhi-weekend', 'WND', 'New Delhi Weekend', 'Weekend Suppliment', 'New Delhi', 1900, NULL, 1902, 1, 1, 0),
  ('common-outside-kerala', 'NRE', 'Common-Outside-Kerala', 'Common-Outside-Kerala', 'Common-Outside-Kerala', 2000, 77, 2005, 0, 0, 1)
ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name), full_name=VALUES(full_name), group_name=VALUES(group_name),
  group_sort=VALUES(group_sort), legacy_id=VALUES(legacy_id), sort_order=VALUES(sort_order), is_active=VALUES(is_active),
  is_supplement=VALUES(is_supplement), is_main=VALUES(is_main);

-- Sub-editions show their main edition's pages for any page number they do not have themselves
UPDATE editions e JOIN editions s ON s.slug = 'alappuzha' SET e.inherit_from_id = s.id WHERE e.slug = 'kuttanad';
UPDATE editions e JOIN editions s ON s.slug = 'alappuzha' SET e.inherit_from_id = s.id WHERE e.slug = 'cherthala';
UPDATE editions e JOIN editions s ON s.slug = 'alappuzha' SET e.inherit_from_id = s.id WHERE e.slug = 'aroor';
UPDATE editions e JOIN editions s ON s.slug = 'alappuzha' SET e.inherit_from_id = s.id WHERE e.slug = 'kayamkulam';
UPDATE editions e JOIN editions s ON s.slug = 'alappuzha' SET e.inherit_from_id = s.id WHERE e.slug = 'mavelikkara';
UPDATE editions e JOIN editions s ON s.slug = 'alappuzha' SET e.inherit_from_id = s.id WHERE e.slug = 'chengannur';
UPDATE editions e JOIN editions s ON s.slug = 'ernakulam' SET e.inherit_from_id = s.id WHERE e.slug = 'tripunithura-mulanthuruthy';
UPDATE editions e JOIN editions s ON s.slug = 'ernakulam' SET e.inherit_from_id = s.id WHERE e.slug = 'aroor-mattanchery';
UPDATE editions e JOIN editions s ON s.slug = 'ernakulam' SET e.inherit_from_id = s.id WHERE e.slug = 'paravur-vypin';
UPDATE editions e JOIN editions s ON s.slug = 'ernakulam' SET e.inherit_from_id = s.id WHERE e.slug = 'aluva-angamaly-kalady';
UPDATE editions e JOIN editions s ON s.slug = 'ernakulam' SET e.inherit_from_id = s.id WHERE e.slug = 'perumbavoor-kolenchery';
UPDATE editions e JOIN editions s ON s.slug = 'ernakulam' SET e.inherit_from_id = s.id WHERE e.slug = 'kothamangalam-muvattupuzha';
UPDATE editions e JOIN editions s ON s.slug = 'idukky' SET e.inherit_from_id = s.id WHERE e.slug = 'highrange';
UPDATE editions e JOIN editions s ON s.slug = 'pathanamthitta' SET e.inherit_from_id = s.id WHERE e.slug = 'adoor-panthalam';
UPDATE editions e JOIN editions s ON s.slug = 'pathanamthitta' SET e.inherit_from_id = s.id WHERE e.slug = 'kozhanchery-ranni';
UPDATE editions e JOIN editions s ON s.slug = 'pathanamthitta' SET e.inherit_from_id = s.id WHERE e.slug = 'thiruvalla';
UPDATE editions e JOIN editions s ON s.slug = 'kottayam' SET e.inherit_from_id = s.id WHERE e.slug = 'changanacherry';
UPDATE editions e JOIN editions s ON s.slug = 'kottayam' SET e.inherit_from_id = s.id WHERE e.slug = 'vaikom';
UPDATE editions e JOIN editions s ON s.slug = 'kottayam' SET e.inherit_from_id = s.id WHERE e.slug = 'pala-erattupetta';
UPDATE editions e JOIN editions s ON s.slug = 'kannur' SET e.inherit_from_id = s.id WHERE e.slug = 'thalassery';
UPDATE editions e JOIN editions s ON s.slug = 'kannur' SET e.inherit_from_id = s.id WHERE e.slug = 'thaliparamba';
UPDATE editions e JOIN editions s ON s.slug = 'kannur' SET e.inherit_from_id = s.id WHERE e.slug = 'payyanur';
UPDATE editions e JOIN editions s ON s.slug = 'kannur' SET e.inherit_from_id = s.id WHERE e.slug = 'iritty';
UPDATE editions e JOIN editions s ON s.slug = 'kasaragod' SET e.inherit_from_id = s.id WHERE e.slug = 'kanhangad';
UPDATE editions e JOIN editions s ON s.slug = 'kollam' SET e.inherit_from_id = s.id WHERE e.slug = 'chathanoor';
UPDATE editions e JOIN editions s ON s.slug = 'kollam' SET e.inherit_from_id = s.id WHERE e.slug = 'sastham-kotta';
UPDATE editions e JOIN editions s ON s.slug = 'kollam' SET e.inherit_from_id = s.id WHERE e.slug = 'kottarakkara';
UPDATE editions e JOIN editions s ON s.slug = 'kollam' SET e.inherit_from_id = s.id WHERE e.slug = 'punalur';
UPDATE editions e JOIN editions s ON s.slug = 'malappuram' SET e.inherit_from_id = s.id WHERE e.slug = 'valanchery';
UPDATE editions e JOIN editions s ON s.slug = 'malappuram' SET e.inherit_from_id = s.id WHERE e.slug = 'tirur';
UPDATE editions e JOIN editions s ON s.slug = 'malappuram' SET e.inherit_from_id = s.id WHERE e.slug = 'ponnani';
UPDATE editions e JOIN editions s ON s.slug = 'malappuram' SET e.inherit_from_id = s.id WHERE e.slug = 'kondotty';
UPDATE editions e JOIN editions s ON s.slug = 'malappuram' SET e.inherit_from_id = s.id WHERE e.slug = 'nilambur';
UPDATE editions e JOIN editions s ON s.slug = 'palakkad' SET e.inherit_from_id = s.id WHERE e.slug = 'chittur';
UPDATE editions e JOIN editions s ON s.slug = 'palakkad' SET e.inherit_from_id = s.id WHERE e.slug = 'vadakkanchery';
UPDATE editions e JOIN editions s ON s.slug = 'palakkad' SET e.inherit_from_id = s.id WHERE e.slug = 'mannarkkad';
UPDATE editions e JOIN editions s ON s.slug = 'palakkad' SET e.inherit_from_id = s.id WHERE e.slug = 'ottapalam';
UPDATE editions e JOIN editions s ON s.slug = 'palakkad' SET e.inherit_from_id = s.id WHERE e.slug = 'pattambi';
UPDATE editions e JOIN editions s ON s.slug = 'palakkad' SET e.inherit_from_id = s.id WHERE e.slug = 'coimbatore';
UPDATE editions e JOIN editions s ON s.slug = 'trivandrum' SET e.inherit_from_id = s.id WHERE e.slug = 'nedumangad';
UPDATE editions e JOIN editions s ON s.slug = 'trivandrum' SET e.inherit_from_id = s.id WHERE e.slug = 'attingal';
UPDATE editions e JOIN editions s ON s.slug = 'trivandrum' SET e.inherit_from_id = s.id WHERE e.slug = 'neyyattinkara';
UPDATE editions e JOIN editions s ON s.slug = 'thrissur' SET e.inherit_from_id = s.id WHERE e.slug = 'chalakkudy';
UPDATE editions e JOIN editions s ON s.slug = 'thrissur' SET e.inherit_from_id = s.id WHERE e.slug = 'guruvayur';
UPDATE editions e JOIN editions s ON s.slug = 'thrissur' SET e.inherit_from_id = s.id WHERE e.slug = 'kodungallur';
UPDATE editions e JOIN editions s ON s.slug = 'thrissur' SET e.inherit_from_id = s.id WHERE e.slug = 'wadakkanchery';
UPDATE editions e JOIN editions s ON s.slug = 'kozhikode' SET e.inherit_from_id = s.id WHERE e.slug = 'ramanattukara';
UPDATE editions e JOIN editions s ON s.slug = 'kozhikode' SET e.inherit_from_id = s.id WHERE e.slug = 'thamarassery';
UPDATE editions e JOIN editions s ON s.slug = 'kozhikode' SET e.inherit_from_id = s.id WHERE e.slug = 'koyilandy';
UPDATE editions e JOIN editions s ON s.slug = 'kozhikode' SET e.inherit_from_id = s.id WHERE e.slug = 'vadakara';
UPDATE editions e JOIN editions s ON s.slug = 'common-outside-kerala' SET e.inherit_from_id = s.id WHERE e.slug = 'mumbai';
UPDATE editions e JOIN editions s ON s.slug = 'common-outside-kerala' SET e.inherit_from_id = s.id WHERE e.slug = 'new-delhi';

-- Editions created before this list (no code) are hidden; their issues stay in the database.
UPDATE editions SET is_active = 0 WHERE code IS NULL;
