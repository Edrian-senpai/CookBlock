import sqlite3
import pandas as pd
from reportlab.lib.pagesizes import letter
from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet
import tempfile
import os
import sys

DB_PATH = os.path.join(os.path.dirname(__file__), 'CookBlock.db')
PDF_PATH = os.path.join(tempfile.gettempdir(), 'selected_recipes.pdf')

def get_connection():
    return sqlite3.connect(DB_PATH)

def fetch_recipes_by_ids(ids):
    conn = get_connection()
    placeholders = ','.join(['?'] * len(ids))
    sql = f'''SELECT r.*, c.name as category_name, u.username,
        GROUP_CONCAT(t.name, ', ') as tags
        FROM recipes r
        LEFT JOIN categories c ON r.category_id = c.id
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN recipe_tags rt ON r.id = rt.recipe_id
        LEFT JOIN tags t ON rt.tag_id = t.id
        WHERE r.id IN ({placeholders})
        GROUP BY r.id'''
    df = pd.read_sql(sql, conn, params=ids)
    conn.close()
    return df

def fetch_favorites_by_user(user_id):
    conn = get_connection()
    sql = '''SELECT r.*, c.name as category_name, u.username,
        GROUP_CONCAT(t.name, ', ') as tags
        FROM favorites f
        JOIN recipes r ON f.recipe_id = r.id
        LEFT JOIN categories c ON r.category_id = c.id
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN recipe_tags rt ON r.id = rt.recipe_id
        LEFT JOIN tags t ON rt.tag_id = t.id
        WHERE f.user_id = ?
        GROUP BY r.id'''
    df = pd.read_sql(sql, conn, params=[user_id])
    conn.close()
    return df

def generate_pdf(recipes_df, pdf_path=PDF_PATH):
    doc = SimpleDocTemplate(pdf_path, pagesize=letter)
    elements = []
    styles = getSampleStyleSheet()
    elements.append(Paragraph('Recipe Collection', styles['Title']))
    elements.append(Spacer(1, 12))
    for idx, row in recipes_df.iterrows():
        elements.append(Paragraph(f"<b>Title:</b> {row['title']}", styles['Heading2']))
        elements.append(Paragraph(f"<b>Category:</b> {row['category_name'] or 'Uncategorized'}", styles['Normal']))
        elements.append(Paragraph(f"<b>Tags:</b> {row['tags'] or 'None'}", styles['Normal']))
        elements.append(Paragraph(f"<b>Created At:</b> {row['created_at']}", styles['Normal']))
        elements.append(Paragraph(f"<b>Description:</b> {row['description']}", styles['Normal']))
        elements.append(Paragraph(f"<b>Ingredients:</b> {row['ingredients']}", styles['Normal']))
        elements.append(Paragraph(f"<b>Instructions:</b> {row['instructions']}", styles['Normal']))
        elements.append(Spacer(1, 18))
    doc.build(elements)
    return pdf_path

def main():
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument('--ids', nargs='+', type=int, help='Recipe IDs to export')
    parser.add_argument('--favorites', type=int, help='User ID to export all favorites')
    args = parser.parse_args()
    if args.ids:
        df = fetch_recipes_by_ids(args.ids)
    elif args.favorites:
        df = fetch_favorites_by_user(args.favorites)
    else:
        print('No recipes selected.', file=sys.stderr)
        sys.exit(1)
    if df.empty:
        print('No recipes found.', file=sys.stderr)
        sys.exit(2)
    pdf_path = generate_pdf(df)
    print(pdf_path)

if __name__ == '__main__':
    main()
