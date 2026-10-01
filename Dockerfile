FROM php:8.2-apache

# 1. ติดตั้ง Python 3, pip และไลบรารีระบบที่จำเป็นสำหรับ MySQL extension
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-venv \
    libmariadb-dev \
    && rm -rf /var/lib/apt/lists/*

# 2. ติดตั้งส่วนขยาย mysqli และ pdo_mysql สำหรับเชื่อมต่อ Database ใน PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql

# 3. คัดลอกการตั้งค่า Apache และเปิดใช้งาน mod_rewrite
COPY apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

# 4. ตั้งค่าโฟลเดอร์ทำงานและคัดลอกไฟล์โค้ดทั้งหมดเข้า Container
WORKDIR /var/www/html
COPY . /var/www/html/

# 5. ติดตั้ง dependencies ของ Python (รวมถึง sentence-transformers และ torch)
RUN pip3 install --no-cache-dir --break-system-packages -r requirements.txt

# 6. เปิดพอร์ต 80 และรัน Apache
EXPOSE 80
CMD ["apache2-foreground"]