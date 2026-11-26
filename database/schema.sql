CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('personal','aluno') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS students (
    id INT UNSIGNED PRIMARY KEY,
    personal_id INT UNSIGNED NOT NULL,
    phone VARCHAR(40) NOT NULL DEFAULT '',
    birth_date DATE NULL,
    anamnesis TEXT NOT NULL,
    due_day TINYINT UNSIGNED NOT NULL,
    FOREIGN KEY (id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (personal_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exercises (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    personal_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    muscle_group VARCHAR(100) NOT NULL,
    video_url VARCHAR(500) NOT NULL DEFAULT '',
    FOREIGN KEY (personal_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS workouts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    created_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    active_student_id INT UNSIGNED NULL UNIQUE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS workout_items (
    workout_id INT UNSIGNED NOT NULL,
    position INT UNSIGNED NOT NULL,
    series ENUM('A','B','C') NOT NULL,
    exercise_id INT UNSIGNED NOT NULL,
    sets SMALLINT UNSIGNED NOT NULL,
    reps VARCHAR(40) NOT NULL,
    PRIMARY KEY (workout_id, position),
    FOREIGN KEY (workout_id) REFERENCES workouts(id) ON DELETE CASCADE,
    FOREIGN KEY (exercise_id) REFERENCES exercises(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    reference_month DATE NOT NULL,
    due_date DATE NOT NULL,
    amount DECIMAL(10,2) UNSIGNED NOT NULL,
    paid_on DATE NULL,
    UNIQUE (student_id, reference_month),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dados fictícios para demonstração. Use SET @fitcontrol_demo = 0 antes da importação para omiti-los.
-- O cadastro de cinco alunos também requer uma conta Personal, totalizando seis contas em users.
START TRANSACTION;
SET @seed_demo = COALESCE(@fitcontrol_demo, 1) = 1 AND NOT EXISTS (SELECT 1 FROM users);
SET @demo_hash = '$2y$10$wA5bUY3Uwj6xPe2TX4UaZeeivEG4E1rxUKbJh1XZNz7AjY2m9K..S';

INSERT INTO users (id, name, email, password_hash, role)
SELECT demo.* FROM (
    SELECT 1 AS id, 'Marina Costa' AS name, 'personal@fitcontrol.test' AS email, @demo_hash AS password_hash, 'personal' AS role
    UNION ALL
    SELECT 2, 'Ana Ribeiro', 'ana@fitcontrol.test', @demo_hash, 'aluno'
    UNION ALL
    SELECT 3, 'Bruno Almeida', 'bruno@fitcontrol.test', @demo_hash, 'aluno'
    UNION ALL
    SELECT 4, 'Camila Santos', 'camila@fitcontrol.test', @demo_hash, 'aluno'
    UNION ALL
    SELECT 5, 'Diego Lima', 'diego@fitcontrol.test', @demo_hash, 'aluno'
    UNION ALL
    SELECT 6, 'Elisa Moura', 'elisa@fitcontrol.test', @demo_hash, 'aluno'
) AS demo
WHERE @seed_demo;

INSERT INTO students (id, personal_id, phone, birth_date, anamnesis, due_day)
SELECT demo.* FROM (
    SELECT 2 AS id, 1 AS personal_id, '(11) 90000-0002' AS phone, '2002-04-18' AS birth_date, 'Objetivo: melhorar o condicionamento físico.' AS anamnesis, 10 AS due_day
    UNION ALL
    SELECT 3, 1, '(11) 90000-0003', '1998-09-22', 'Objetivo: ganhar força com progressão gradual.', 15
    UNION ALL
    SELECT 4, 1, '(11) 90000-0004', '2001-07-03', 'Objetivo: iniciar uma rotina de exercícios.', 20
    UNION ALL
    SELECT 5, 1, '(11) 90000-0005', '1996-12-12', 'Objetivo: retomar os treinos após uma pausa.', 30
    UNION ALL
    SELECT 6, 1, '(11) 90000-0006', '2000-02-28', 'Objetivo: manter uma rotina de treinos.', 25
) AS demo
WHERE @seed_demo;

INSERT INTO exercises (id, personal_id, name, muscle_group, video_url)
SELECT demo.* FROM (
    SELECT 1 AS id, 1 AS personal_id, 'Agachamento livre' AS name, 'Pernas' AS muscle_group, 'https://www.youtube.com/results?search_query=agachamento+livre+t%C3%A9cnica' AS video_url
    UNION ALL
    SELECT 2, 1, 'Puxada frontal', 'Costas', 'https://www.youtube.com/results?search_query=puxada+frontal+t%C3%A9cnica'
    UNION ALL
    SELECT 3, 1, 'Supino reto', 'Peito', 'https://www.youtube.com/results?search_query=supino+reto+t%C3%A9cnica'
    UNION ALL
    SELECT 4, 1, 'Prancha abdominal', 'Abdômen', 'https://www.youtube.com/results?search_query=prancha+abdominal+t%C3%A9cnica'
    UNION ALL
    SELECT 5, 1, 'Elevação lateral', 'Ombros', 'https://www.youtube.com/results?search_query=eleva%C3%A7%C3%A3o+lateral+t%C3%A9cnica'
) AS demo
WHERE @seed_demo;

INSERT INTO workouts (id, student_id, title, created_on, active_student_id)
SELECT demo.* FROM (
    SELECT 1 AS id, 2 AS student_id, 'Adaptação inicial' AS title, '2026-10-05 12:00:00' AS created_on, NULL AS active_student_id
    UNION ALL
    SELECT 2, 2, 'Força e condicionamento', '2026-11-05 12:00:00', 2
    UNION ALL
    SELECT 3, 3, 'Treino de costas', '2026-11-10 12:00:00', 3
    UNION ALL
    SELECT 4, 4, 'Treino de força inicial', '2026-11-12 12:00:00', 4
    UNION ALL
    SELECT 5, 5, 'Retorno gradual', '2026-11-14 12:00:00', 5
) AS demo
WHERE @seed_demo;

INSERT INTO workout_items (workout_id, position, series, exercise_id, sets, reps)
SELECT demo.* FROM (
    SELECT 1 AS workout_id, 0 AS position, 'A' AS series, 4 AS exercise_id, 3 AS sets, '20 segundos' AS reps
    UNION ALL
    SELECT 2, 0, 'A', 1, 3, '10 a 12'
    UNION ALL
    SELECT 3, 0, 'B', 2, 3, '10'
    UNION ALL
    SELECT 4, 0, 'A', 3, 3, '8 a 10'
    UNION ALL
    SELECT 5, 0, 'C', 5, 3, '12'
) AS demo
WHERE @seed_demo;

INSERT INTO payments (id, student_id, reference_month, due_date, amount, paid_on)
SELECT demo.* FROM (
    SELECT 1 AS id, 2 AS student_id, '2026-10-01' AS reference_month, '2026-10-10' AS due_date, '150.00' AS amount, '2026-10-09' AS paid_on
    UNION ALL
    SELECT 2, 2, '2026-11-01', '2026-11-10', '150.00', '2026-11-10'
    UNION ALL
    SELECT 3, 3, '2026-11-01', '2026-11-15', '160.00', NULL
    UNION ALL
    SELECT 4, 4, '2026-11-01', '2026-11-20', '150.00', '2026-11-18'
    UNION ALL
    SELECT 5, 5, '2026-11-01', '2026-11-30', '170.00', NULL
) AS demo
WHERE @seed_demo;

COMMIT;
SET @demo_hash = NULL;
SET @seed_demo = NULL;
