import os
import pymysql
import pandas as pd
from fastapi import FastAPI, BackgroundTasks
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

app = FastAPI(title="Recommendation Service")

# ค่าเชื่อมต่อ TiDB Cloud
DB_HOST = os.getenv("DB_HOST", "gateway01.ap-southeast-1.prod.aws.tidbcloud.com")
DB_USER = os.getenv("DB_USER", "4JodNqEkbc1nEbH.root")
DB_PASS = os.getenv("DB_PASS", "zF4DHIXiUrHylslj")
DB_NAME = os.getenv("DB_NAME", "thai_novel")
DB_PORT = int(os.getenv("DB_PORT", 4000))

def run_calculation(category_id: int):
    try:
        conn = pymysql.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASS,
            database=DB_NAME,
            port=DB_PORT,
            charset="utf8mb4",
            ssl={}
        )
        
        sql = "SELECT Book_id, Title, Blurb FROM book WHERE Category_id = %s ORDER BY Book_id"
        df = pd.read_sql(sql, conn, params=(category_id,))
        
        if len(df) <= 1:
            conn.close()
            return
            
        cursor = conn.cursor()
        cursor.execute("DELETE FROM recommendation_sentence_same_category WHERE book_id IN (SELECT Book_id FROM book WHERE Category_id = %s)", (category_id,))
        
        cursor.execute("SELECT COALESCE(MAX(id), 0) FROM recommendation_sentence_same_category")
        row = cursor.fetchone()
        current_id = row[0] if row else 0
        
        # รวม Title และ Blurb เพื่อวิเคราะห์เนื้อหา
        df["content"] = df["Title"].fillna("").astype(str) + " " + df["Blurb"].fillna("").astype(str)
        
        # คำนวณด้วย TF-IDF (ประหยัด RAM สูงมาก และเร็วระดับมิลลิวินาที)
        vectorizer = TfidfVectorizer()
        tfidf_matrix = vectorizer.fit_transform(df["content"].tolist())
        similarity_matrix = cosine_similarity(tfidf_matrix, tfidf_matrix)
        book_ids = df["Book_id"].tolist()
        
        for i, book_id in enumerate(book_ids):
            scores = similarity_matrix[i]
            result = [(int(book_ids[j]), float(score)) for j, score in enumerate(scores) if book_ids[j] != book_id]
            result.sort(key=lambda x: x[1], reverse=True)
            
            for recommend_id, score in result[:5]:
                current_id += 1
                cursor.execute("""
                    INSERT INTO recommendation_sentence_same_category (id, book_id, recommend_book_id, similarity)
                    VALUES (%s, %s, %s, %s)
                """, (current_id, int(book_id), int(recommend_id), round(score, 4)))
                
        conn.commit()
        cursor.close()
        conn.close()
        print(f"✅ คำนวณและบันทึกผลหมวดหมู่ {category_id} สำเร็จ")
    except Exception as e:
        print(f"❌ Error: {e}")

@app.get("/")
def home():
    return {"status": "Recommendation API is Running"}

@app.get("/calculate")
def trigger_calculate(category_id: int, background_tasks: BackgroundTasks):
    background_tasks.add_task(run_calculation, category_id)
    return {"status": "started", "category_id": category_id}