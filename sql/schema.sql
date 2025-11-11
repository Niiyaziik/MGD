-- Округа
CREATE TABLE districts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  district VARCHAR(255) NOT NULL,
  street VARCHAR(255) NOT NULL,
  house VARCHAR(255) Not NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  deleted_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Кандидаты (принадлежат округу)
CREATE TABLE candidates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  surname VARCHAR(255),
  name VARCHAR(255),
  patronymic VARCHAR(255) NULL,
  phone VARCHAR(32) UNIQUE,
  vk_id VARCHAR(100) UNIQUE,
  district_id INT UNSIGNED NOT NULL,
  photo VARCHAR(255) NULL,
  email VARCHAR(255) NULL,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  deleted_at TIMESTAMP NULL,
  CONSTRAINT fk_candidates_district FOREIGN KEY (district_id) REFERENCES districts(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Пользователи
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  surname VARCHAR(255),
  name VARCHAR(255),
  patronymic VARCHAR(255) NULL,
  phone VARCHAR(32) UNIQUE,
  link_vk VARCHAR(255) UNIQUE,
  district_id INT UNSIGNED NOT NULL,
  auth_method ENUM("ВК", "МАКС", "Телефон"),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  deleted_at TIMESTAMP NULL,
  CONSTRAINT fk_users_district FOREIGN KEY (district_id) REFERENCES districts(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Администраторы
CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  login VARCHAR(255) UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  deleted_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Голоса (каждый пользователь может проголосовать один раз)
CREATE TABLE votes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  candidate_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  UNIQUE KEY uniq_user (user_id),
  KEY idx_candidate (candidate_id),
  CONSTRAINT fk_votes_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_votes_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
