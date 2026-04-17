ALTER TABLE posts
  ADD COLUMN proximo_post_id INT NULL AFTER destaque;

ALTER TABLE posts
  ADD INDEX idx_posts_proximo_post_id (proximo_post_id);

ALTER TABLE posts
  ADD CONSTRAINT fk_posts_proximo_post
    FOREIGN KEY (proximo_post_id)
    REFERENCES posts(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;
