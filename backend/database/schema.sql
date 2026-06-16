create table if not exists admins (
  id int unsigned auto_increment primary key,
  name varchar(50) not null,
  phone varchar(30) not null unique,
  password_hash varchar(255) not null,
  role varchar(20) not null default 'super',
  created_at timestamp default current_timestamp
) engine=InnoDB default charset=utf8mb4;

create table if not exists users (
  id int unsigned auto_increment primary key,
  nickname varchar(50) not null,
  phone varchar(30) not null unique,
  province varchar(30) null,
  password_hash varchar(255) not null,
  access_type enum('trial','full') not null default 'trial',
  start_date date not null,
  status enum('active','disabled') not null default 'active',
  login_count int unsigned not null default 0,
  last_login_at datetime null,
  created_at timestamp default current_timestamp,
  updated_at timestamp default current_timestamp on update current_timestamp
) engine=InnoDB default charset=utf8mb4;

create table if not exists unlock_codes (
  id int unsigned auto_increment primary key,
  code varchar(60) not null unique,
  access_type enum('full') not null default 'full',
  assigned_phone varchar(30) null,
  used_by_user_id int unsigned null,
  used_at datetime null,
  expires_at datetime null,
  created_at timestamp default current_timestamp
) engine=InnoDB default charset=utf8mb4;

create table if not exists study_progress (
  id int unsigned auto_increment primary key,
  user_id int unsigned not null,
  day_no tinyint unsigned not null,
  task_vocab tinyint(1) not null default 0,
  task_lecture tinyint(1) not null default 0,
  task_workbook tinyint(1) not null default 0,
  task_test tinyint(1) not null default 0,
  checkin_image varchar(255) null,
  completed_at datetime null,
  updated_at timestamp default current_timestamp on update current_timestamp,
  unique key uniq_user_day (user_id, day_no),
  index idx_user_day (user_id, day_no)
) engine=InnoDB default charset=utf8mb4;

create table if not exists test_records (
  id int unsigned auto_increment primary key,
  user_id int unsigned not null,
  day_no tinyint unsigned not null,
  test_type varchar(30) not null default 'daily',
  score decimal(5,2) not null default 0,
  total_score decimal(5,2) not null default 100,
  wrong_count int unsigned not null default 0,
  weak_points varchar(255) null,
  advice varchar(255) null,
  created_at timestamp default current_timestamp,
  index idx_user_day (user_id, day_no)
) engine=InnoDB default charset=utf8mb4;

create table if not exists wrong_questions (
  id int unsigned auto_increment primary key,
  user_id int unsigned not null,
  day_no tinyint unsigned not null,
  source_type enum('workbook','daily_test','stage_test') not null,
  question_title varchar(255) not null,
  knowledge_point varchar(100) null,
  wrong_reason varchar(100) null,
  remedy_task varchar(255) null,
  review_count int unsigned not null default 0,
  status enum('open','reviewed','closed') not null default 'open',
  created_at timestamp default current_timestamp,
  updated_at timestamp default current_timestamp on update current_timestamp,
  index idx_user_status (user_id, status)
) engine=InnoDB default charset=utf8mb4;
