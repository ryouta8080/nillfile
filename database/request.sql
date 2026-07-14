CREATE TABLE request_idea (
  request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id BIGINT UNSIGNED NOT NULL,
  patreon_id VARCHAR(255) NOT NULL,
  patron_name VARCHAR(255) NULL,
  patron_status_at_request VARCHAR(64) NULL,
  tier_id_at_request VARCHAR(255) NULL,
  is_paid_at_request TINYINT NOT NULL DEFAULT 0,
  request_text TEXT NOT NULL,
  category VARCHAR(64) NULL,
  request_type VARCHAR(32) NOT NULL DEFAULT 'other',
  is_nsfw TINYINT NOT NULL DEFAULT 0,
  favorite_flag TINYINT NOT NULL DEFAULT 0,
  done_flag TINYINT NOT NULL DEFAULT 0,
  withdrawn_flag TINYINT NOT NULL DEFAULT 0,
  hidden_flag TINYINT NOT NULL DEFAULT 0,
  admin_viewed_datetime DATETIME NULL,
  admin_memo TEXT NULL,
  content_id BIGINT UNSIGNED NULL,
  reply_text TEXT NULL,
  reply_visible_flag TINYINT NOT NULL DEFAULT 0,
  del_flag TINYINT NOT NULL DEFAULT 0,
  upd_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  reg_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (request_id),
  KEY idx_request_idea_member_date (member_id, reg_datetime),
  KEY idx_request_idea_admin (done_flag, favorite_flag, withdrawn_flag, hidden_flag, reg_datetime),
  KEY idx_request_idea_content_id (content_id),
  KEY idx_request_idea_del_flag (del_flag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE request_setting (
  setting_id INT UNSIGNED NOT NULL,
  accept_flag TINYINT NOT NULL DEFAULT 1,
  description_text TEXT NULL,
  thanks_text TEXT NULL,
  max_length INT UNSIGNED NOT NULL DEFAULT 2000,
  monthly_limit INT UNSIGNED NOT NULL DEFAULT 0,
  cooldown_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  paid_only_flag TINYINT NOT NULL DEFAULT 0,
  admin_bypass_flag TINYINT NOT NULL DEFAULT 1,
  del_flag TINYINT NOT NULL DEFAULT 0,
  upd_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  reg_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_id),
  KEY idx_request_setting_del_flag (del_flag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE request_type_setting (
  type_code VARCHAR(32) NOT NULL,
  type_label VARCHAR(64) NOT NULL,
  enabled_flag TINYINT NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  del_flag TINYINT NOT NULL DEFAULT 0,
  upd_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  reg_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (type_code),
  KEY idx_request_type_setting_display (enabled_flag, sort_order),
  KEY idx_request_type_setting_del_flag (del_flag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO request_setting (
  setting_id,
  accept_flag,
  description_text,
  thanks_text,
  max_length,
  monthly_limit,
  cooldown_minutes,
  paid_only_flag,
  admin_bypass_flag
) VALUES (
  1,
  1,
  'リクエスト内容と、必要であれば参考情報を入力してください。',
  'リクエストを受け付けました。',
  2000,
  0,
  0,
  0,
  1
);

INSERT INTO request_type_setting (
  type_code,
  type_label,
  enabled_flag,
  sort_order
) VALUES
  ('image', '画像', 1, 10),
  ('movie', '動画', 0, 20),
  ('vr', 'VR', 0, 30),
  ('other', 'その他', 0, 40);
