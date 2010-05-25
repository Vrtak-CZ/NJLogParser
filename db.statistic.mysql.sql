-- TOP10 message count
SELECT COUNT(`id`),`name` FROM `data` WHERE `type`="message" GROUP BY `name` HAVING COUNT(`name`)>0 ORDER BY COUNT(`id`) DESC LIMIT 10