-- Adminer 4.7.3 PostgreSQL dump

DROP TABLE IF EXISTS "addresses";
DROP SEQUENCE IF EXISTS addresses_id_seq;
CREATE SEQUENCE addresses_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 START 1 CACHE 1;

CREATE TABLE "public"."addresses" (
    "id" integer DEFAULT nextval('addresses_id_seq') NOT NULL,
    "city" character(100) NOT NULL,
    "street" character(255) NOT NULL,
    "house" character(20) NOT NULL,
    "apartment" character(20) NOT NULL,
    CONSTRAINT "addresses_id" PRIMARY KEY ("id")
) WITH (oids = false);

INSERT INTO "addresses" ("id", "city", "street", "house", "apartment") VALUES
(1,	'Москва                                                                                              ',	'ул. Ленина                                                                                                                                                                                                                                                     ',	'10                  ',	'5                   '),
(2,	'Санкт-Петербург                                                                                     ',	'Невский пр.                                                                                                                                                                                                                                                    ',	'25                  ',	'12                  '),
(3,	'Казань                                                                                              ',	'ул. Баумана                                                                                                                                                                                                                                                    ',	'7                   ',	'3                   ');

DROP TABLE IF EXISTS "authors";
DROP SEQUENCE IF EXISTS authors_id_seq;
CREATE SEQUENCE authors_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 START 1 CACHE 1;

CREATE TABLE "public"."authors" (
    "id" integer DEFAULT nextval('authors_id_seq') NOT NULL,
    "full_name" character(255) NOT NULL,
    "birth_year" integer NOT NULL,
    CONSTRAINT "authors_id" PRIMARY KEY ("id")
) WITH (oids = false);

INSERT INTO "authors" ("id", "full_name", "birth_year") VALUES
(1,	'Лев Толстой                                                                                                                                                                                                                                                    ',	1828),
(2,	'Федор Достоевский                                                                                                                                                                                                                                              ',	1821),
(3,	'Джоан Роулинг                                                                                                                                                                                                                                                  ',	1965),
(4,	'Михаил Булгаков                                                                                                                                                                                                                                                ',	1891),
(5,	'Александр Пушкин                                                                                                                                                                                                                                               ',	1799),
(6,	'Джордж Оруэлл                                                                                                                                                                                                                                                  ',	1903),
(7,	'Артур Конан Дойл                                                                                                                                                                                                                                               ',	1859),
(8,	'Рэй Брэдбери                                                                                                                                                                                                                                                   ',	1920);

DROP TABLE IF EXISTS "book_loans";
DROP SEQUENCE IF EXISTS book_loans_id_seq;
CREATE SEQUENCE book_loans_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 START 1 CACHE 1;

CREATE TABLE "public"."book_loans" (
    "id" integer DEFAULT nextval('book_loans_id_seq') NOT NULL,
    "book_id" integer NOT NULL,
    "subscriber_id" integer,
    "loan_date" date DEFAULT '(''now''::text)',
    "return_date" date,
    CONSTRAINT "book_loans_id" PRIMARY KEY ("id"),
    CONSTRAINT "fk_loan_book" FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE NOT DEFERRABLE,
    CONSTRAINT "fk_loan_subscriber" FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE NOT DEFERRABLE
) WITH (oids = false);

INSERT INTO "book_loans" ("id", "book_id", "subscriber_id", "loan_date", "return_date") VALUES
(2,	5,	2,	'2026-10-24',	'2026-10-24'),
(1,	2,	1,	'2026-10-21',	'2026-10-24'),
(4,	6,	2,	'2026-10-24',	'2026-10-24'),
(5,	10,	3,	'2026-10-24',	'2026-10-24'),
(6,	3,	2,	'2026-10-24',	'2026-10-25'),
(7,	2,	4,	'2026-10-24',	'2026-10-26'),
(8,	4,	2,	'2026-10-26',	NULL);

DROP TABLE IF EXISTS "books";
DROP SEQUENCE IF EXISTS books_id_seq;
CREATE SEQUENCE books_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 START 1 CACHE 1;

CREATE TABLE "public"."books" (
    "id" integer DEFAULT nextval('books_id_seq') NOT NULL,
    "title" character(255) NOT NULL,
    "author_id" integer NOT NULL,
    "genre_id" integer NOT NULL,
    "publisher_id" integer NOT NULL,
    "year_published" integer NOT NULL,
    "inventory_number" character(50) NOT NULL,
    "status" character(20) NOT NULL,
    CONSTRAINT "books_id" PRIMARY KEY ("id"),
    CONSTRAINT "fk_book_author" FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE SET NULL NOT DEFERRABLE,
    CONSTRAINT "fk_book_genre" FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE SET NULL NOT DEFERRABLE,
    CONSTRAINT "fk_book_publisher" FOREIGN KEY (publisher_id) REFERENCES publishers(id) ON DELETE SET NULL NOT DEFERRABLE
) WITH (oids = false);

INSERT INTO "books" ("id", "title", "author_id", "genre_id", "publisher_id", "year_published", "inventory_number", "status") VALUES
(1,	'Война и мир                                                                                                                                                                                                                                                    ',	1,	1,	1,	1869,	'INV-001                                           ',	'available           '),
(5,	'Шерлок Холмс                                                                                                                                                                                                                                                   ',	3,	3,	2,	1892,	'INV-005                                           ',	'available           '),
(3,	'Преступление и наказание                                                                                                                                                                                                                                       ',	2,	1,	1,	1866,	'INV-003                                           ',	'available           '),
(4,	'Гарри Поттер и философский камень                                                                                                                                                                                                                              ',	3,	2,	3,	1997,	'INV-004                                           ',	'issued              '),
(7,	'Евгений Онегин                                                                                                                                                                                                                                                 ',	5,	5,	7,	1833,	'INV-007                                           ',	'available           '),
(8,	'1984                                                                                                                                                                                                                                                           ',	6,	6,	4,	1949,	'INV-008                                           ',	'available           '),
(9,	'Приключения Шерлока Холмса                                                                                                                                                                                                                                     ',	7,	7,	2,	1892,	'INV-009                                           ',	'available           '),
(10,	'451 градус по Фаренгейту                                                                                                                                                                                                                                       ',	8,	8,	2,	1953,	'INV-010                                           ',	'available           '),
(2,	'Анна Каренина                                                                                                                                                                                                                                                  ',	1,	1,	2,	1877,	'INV-002                                           ',	'available           '),
(6,	'Мастер и Маргарита                                                                                                                                                                                                                                             ',	4,	4,	1,	1967,	'INV-006                                           ',	'available           ');

DROP TABLE IF EXISTS "genres";
DROP SEQUENCE IF EXISTS genres_id_seq;
CREATE SEQUENCE genres_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 START 1 CACHE 1;

CREATE TABLE "public"."genres" (
    "id" integer DEFAULT nextval('genres_id_seq') NOT NULL,
    "name" character(100) NOT NULL,
    CONSTRAINT "genres_id" PRIMARY KEY ("id")
) WITH (oids = false);

INSERT INTO "genres" ("id", "name") VALUES
(1,	'Роман                                                                                               '),
(2,	'Фантастика                                                                                          '),
(3,	'Детектив                                                                                            '),
(4,	'Мистика                                                                                             '),
(5,	'Поэзия                                                                                              '),
(6,	'Антиутопия                                                                                          '),
(7,	'Приключения                                                                                         '),
(8,	'Научная фантастика                                                                                  ');

DROP TABLE IF EXISTS "publishers";
DROP SEQUENCE IF EXISTS publishers_id_seq;
CREATE SEQUENCE publishers_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 START 1 CACHE 1;

CREATE TABLE "public"."publishers" (
    "id" integer DEFAULT nextval('publishers_id_seq') NOT NULL,
    "name" character(255) NOT NULL,
    "city" character(100) NOT NULL,
    CONSTRAINT "publishers_id" PRIMARY KEY ("id")
) WITH (oids = false);

INSERT INTO "publishers" ("id", "name", "city") VALUES
(1,	'АСТ                                                                                                                                                                                                                                                            ',	'Москва                                                                                              '),
(2,	'Эксмо                                                                                                                                                                                                                                                          ',	'Москва                                                                                              '),
(3,	'Азбука                                                                                                                                                                                                                                                         ',	'Санкт-Петербург                                                                                     '),
(4,	'Азбука-Аттикус                                                                                                                                                                                                                                                 ',	'Санкт-Петербург                                                                                     '),
(5,	'АСТ                                                                                                                                                                                                                                                            ',	'Москва                                                                                              '),
(6,	'Эксмо                                                                                                                                                                                                                                                          ',	'Москва                                                                                              '),
(7,	'Просвещение                                                                                                                                                                                                                                                    ',	'Москва                                                                                              ');

DROP TABLE IF EXISTS "subscribers";
DROP SEQUENCE IF EXISTS subscribers_id_seq;
CREATE SEQUENCE subscribers_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 START 1 CACHE 1;

CREATE TABLE "public"."subscribers" (
    "id" integer DEFAULT nextval('subscribers_id_seq') NOT NULL,
    "full_name" character(255) NOT NULL,
    "phone" character(20) NOT NULL,
    "address_id" integer NOT NULL,
    CONSTRAINT "subscribers_id" PRIMARY KEY ("id"),
    CONSTRAINT "subscribers_address_id_fkey" FOREIGN KEY (address_id) REFERENCES addresses(id) NOT DEFERRABLE
) WITH (oids = false);

INSERT INTO "subscribers" ("id", "full_name", "phone", "address_id") VALUES
(1,	'Петров Ярослав Иванович                                                                                                                                                                                                                                        ',	'+7-999-111-22-33    ',	1),
(2,	'Любимова Светлана Петровна                                                                                                                                                                                                                                     ',	'+7-999-444-55-66    ',	2),
(3,	'Родина Марина Викторовна                                                                                                                                                                                                                                       ',	'+7-999-777-88-99    ',	3),
(4,	'Иванов Иван Иванович                                                                                                                                                                                                                                           ',	'+7(999)111-22-33    ',	1),
(5,	'Смирнова Анна Сергеевна                                                                                                                                                                                                                                        ',	'+7(999)222-33-44    ',	2);

-- 2026-10-27 13:08:13.982185+00
