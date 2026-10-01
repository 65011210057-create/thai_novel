import os
import sys
import pymysql
import pandas as pd
from sentence_transformers import SentenceTransformer
from sklearn.metrics.pairwise import cosine_similarity

# =====================================
# รับค่า Category_id จากพารามิเตอร์ภายนอก
# =====================================
if len(sys.argv) < 2:
    print("Error: ไม่พบ Category_id กรุณาระบุ เช่น python sentence_same_category.py 1")
    sys.exit(1)

try:
    target_category_id = int(sys.argv[1])
except ValueError:
    print("Error: Category_id ต้องเป็นตัวเลขจำนวนเต็มเท่านั้น")
    sys.exit(1)

print(f"กำลังเริ่มคำนวณ Recommendation เฉพาะ Category_id: {target_category_id}")

# =====================================
# Database Configuration จาก Environment Variables
# =====================================
db_host = os.getenv("DB_HOST", "localhost")
db_user = os.getenv("DB_USER", "root")
db_pass = os.getenv("DB_PASS", "")
db_name = os.getenv("DB_NAME", "thai_novel")
db_port = int(os.getenv("DB_PORT", 3306))

# ตรวจสอบการตั้งค่า SSL (สำหรับ TiDB Cloud บน Render)
connect_kwargs = {
    "host": db_host,
    "user": db_user,
    "password": db_pass,
    "database": db_name,
    "port": db_port,
    "charset": "utf8mb4"
}

# หากไม่ใช่ localhost (อยู่บน Render/TiDB Cloud) ให้เปิดใช้งาน SSL
if db_host != "localhost" and db_host != "127.0.0.1":
    ca_path = "/etc/ssl/certs/ca-certificates.crt"
    if os.path.exists(ca_path):
        connect_kwargs["ssl"] = {"ca": ca_path}
    else:
        connect_kwargs["ssl"] = {}

try:
    conn = pymysql.connect(**connect_kwargs)
    print("เชื่อมต่อฐานข้อมูลสำเร็จ")
except Exception as e:
    print(f"Error เชื่อมต่อฐานข้อมูลล้มเหลว: {e}")
    sys.exit(1)

# =====================================
# ดึงเฉพาะหนังสือในหมวดหมู่ที่ระบุ
# =====================================
sql = """
SELECT
    Book_id,
    Title,
    Blurb,
    Category_id
FROM book
WHERE Category_id = %s
ORDER BY Book_id
"""

df = pd.read_sql(sql, conn, params=(target_category_id,))

if df.empty or len(df) <= 1:
    print(f"หนังสือในหมวดหมู่ {target_category_id} มีไม่เพียงพอต่อการคำนวณ (มี {len(df)} เล่ม)")
    conn.close()
    sys.exit(0)

print(f"พบหนังสือในหมวดนี้ทั้งหมด: {len(df)} เล่ม")

# =====================================
# โหลด Sentence Transformer
# =====================================
print("Loading Embedding model...")
model = SentenceTransformer(
    "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2"
)
print("Embedding model loaded")

# =====================================
# ลบข้อมูลเดิมเฉพาะหนังสือในหมวดนี้
# =====================================
cursor = conn.cursor()

cursor.execute("""
    DELETE FROM recommendation_sentence_same_category
    WHERE book_id IN (SELECT Book_id FROM book WHERE Category_id = %s)
""", (target_category_id,))

# =====================================
# รวมข้อความ Title + Blurb
# =====================================
df["content"] = (
    df["Title"].fillna("").astype(str)
    + " "
    + df["Blurb"].fillna("").astype(str)
)

# =====================================
# สร้าง Embedding & Cosine Similarity
# =====================================
print("Creating Embedding vectors...")
vectors = model.encode(
    df["content"].tolist(),
    show_progress_bar=False
)

similarity_matrix = cosine_similarity(vectors)
book_ids = df["Book_id"].tolist()

total_saved = 0

# =====================================
# คำนวณและบันทึก Top 5 ให้หนังสือแต่ละเล่มในหมวดนี้
# =====================================
for i, book_id in enumerate(book_ids):
    scores = similarity_matrix[i]
    result = []

    for j, score in enumerate(scores):
        if book_ids[j] != book_id:
            result.append((int(book_ids[j]), float(score)))

    result.sort(key=lambda x: x[1], reverse=True)
    top_books = result[:5]

    for recommend_id, score in top_books:
        cursor.execute("""
            INSERT INTO recommendation_sentence_same_category
            (
                book_id,
                recommend_book_id,
                similarity
            )
            VALUES (%s, %s, %s)
        """, (
            int(book_id),
            int(recommend_id),
            round(score, 4)
        ))
        total_saved += 1

# =====================================
# Commit และปิดการเชื่อมต่อ
# =====================================
conn.commit()
cursor.close()
conn.close()

print(f"\nคำนวณหมวด {target_category_id} เสร็จสมบูรณ์ บันทึกทั้งหมด {total_saved} รายการ")
