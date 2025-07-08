import sqlite3
import pandas as pd
import matplotlib.pyplot as plt
from reportlab.lib.pagesizes import letter
from reportlab.pdfgen import canvas
from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer, Image
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet
import tempfile
import os

DB_PATH = os.path.join(os.path.dirname(__file__), 'CookBlock.db')
PDF_PATH = os.path.join(tempfile.gettempdir(), 'recipe_report.pdf')

# Connect to the SQLite database
def get_connection():
    return sqlite3.connect(DB_PATH)

def fetch_stats():
    conn = get_connection()
    stats = {}
    # Total users
    stats['total_users'] = pd.read_sql('SELECT COUNT(*) as count FROM users', conn)['count'][0]
    # Total recipes
    stats['total_recipes'] = pd.read_sql('SELECT COUNT(*) as count FROM recipes', conn)['count'][0]
    # Total categories
    stats['total_categories'] = pd.read_sql('SELECT COUNT(*) as count FROM categories', conn)['count'][0]
    # Total favorites
    stats['total_favorites'] = pd.read_sql('SELECT COUNT(*) as count FROM favorites', conn)['count'][0]
    # Recipes per category
    stats['recipes_per_category'] = pd.read_sql('SELECT c.name, COUNT(r.id) as count FROM categories c LEFT JOIN recipes r ON c.id = r.category_id GROUP BY c.id', conn)
    # Top users by recipes
    stats['top_users'] = pd.read_sql('SELECT u.username, COUNT(r.id) as count FROM users u LEFT JOIN recipes r ON u.id = r.user_id GROUP BY u.id ORDER BY count DESC LIMIT 5', conn)
    # Most favorited recipes
    stats['top_recipes'] = pd.read_sql('SELECT r.title, COUNT(f.id) as count FROM recipes r LEFT JOIN favorites f ON r.id = f.recipe_id GROUP BY r.id ORDER BY count DESC LIMIT 5', conn)
    conn.close()
    return stats

def create_charts(stats):
    chart_paths = {}
    # Recipes per category
    fig, ax = plt.subplots()
    stats['recipes_per_category'].plot(kind='bar', x='name', y='count', legend=False, ax=ax)
    ax.set_title('Recipes per Category')
    ax.set_ylabel('Number of Recipes')
    chart1 = os.path.join(tempfile.gettempdir(), 'recipes_per_category.png')
    plt.tight_layout()
    plt.savefig(chart1)
    plt.close(fig)
    chart_paths['recipes_per_category'] = chart1
    # Top users by recipes
    fig, ax = plt.subplots()
    stats['top_users'].plot(kind='bar', x='username', y='count', legend=False, ax=ax)
    ax.set_title('Top Users by Recipes')
    ax.set_ylabel('Number of Recipes')
    chart2 = os.path.join(tempfile.gettempdir(), 'top_users.png')
    plt.tight_layout()
    plt.savefig(chart2)
    plt.close(fig)
    chart_paths['top_users'] = chart2
    # Most favorited recipes
    fig, ax = plt.subplots()
    stats['top_recipes'].plot(kind='bar', x='title', y='count', legend=False, ax=ax)
    ax.set_title('Most Favorited Recipes')
    ax.set_ylabel('Number of Favorites')
    chart3 = os.path.join(tempfile.gettempdir(), 'top_recipes.png')
    plt.tight_layout()
    plt.savefig(chart3)
    plt.close(fig)
    chart_paths['top_recipes'] = chart3
    return chart_paths

def generate_pdf(stats, chart_paths, pdf_path=PDF_PATH):
    doc = SimpleDocTemplate(pdf_path, pagesize=letter)
    elements = []
    styles = getSampleStyleSheet()
    elements.append(Paragraph('Recipe Management System Report', styles['Title']))
    elements.append(Spacer(1, 12))
    # Summary Table
    summary_data = [
        ['Total Users', stats['total_users']],
        ['Total Recipes', stats['total_recipes']],
        ['Total Categories', stats['total_categories']],
        ['Total Favorites', stats['total_favorites']],
    ]
    summary_table = Table(summary_data, hAlign='LEFT')
    summary_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), colors.grey),
        ('TEXTCOLOR', (0, 0), (-1, 0), colors.whitesmoke),
        ('ALIGN', (0, 0), (-1, -1), 'LEFT'),
        ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
        ('BOTTOMPADDING', (0, 0), (-1, 0), 12),
        ('BACKGROUND', (0, 1), (-1, -1), colors.beige),
    ]))
    elements.append(summary_table)
    elements.append(Spacer(1, 24))
    # Charts
    for title, path in [
        ('Recipes per Category', chart_paths['recipes_per_category']),
        ('Top Users by Recipes', chart_paths['top_users']),
        ('Most Favorited Recipes', chart_paths['top_recipes'])
    ]:
        elements.append(Paragraph(title, styles['Heading2']))
        elements.append(Image(path, width=400, height=200))
        elements.append(Spacer(1, 12))
    # Top Users Table
    elements.append(Paragraph('Top Users by Recipes', styles['Heading2']))
    user_data = [['Username', 'Recipes']]
    user_data += stats['top_users'].values.tolist()
    user_table = Table(user_data, hAlign='LEFT')
    user_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), colors.grey),
        ('TEXTCOLOR', (0, 0), (-1, 0), colors.whitesmoke),
        ('ALIGN', (0, 0), (-1, -1), 'LEFT'),
        ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
        ('BOTTOMPADDING', (0, 0), (-1, 0), 12),
        ('BACKGROUND', (0, 1), (-1, -1), colors.beige),
    ]))
    elements.append(user_table)
    elements.append(Spacer(1, 12))
    # Top Recipes Table
    elements.append(Paragraph('Most Favorited Recipes', styles['Heading2']))
    recipe_data = [['Recipe Title', 'Favorites']]
    recipe_data += stats['top_recipes'].values.tolist()
    recipe_table = Table(recipe_data, hAlign='LEFT')
    recipe_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), colors.grey),
        ('TEXTCOLOR', (0, 0), (-1, 0), colors.whitesmoke),
        ('ALIGN', (0, 0), (-1, -1), 'LEFT'),
        ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
        ('BOTTOMPADDING', (0, 0), (-1, 0), 12),
        ('BACKGROUND', (0, 1), (-1, -1), colors.beige),
    ]))
    elements.append(recipe_table)
    doc.build(elements)
    return pdf_path

def main():
    stats = fetch_stats()
    chart_paths = create_charts(stats)
    pdf_path = generate_pdf(stats, chart_paths)
    print(pdf_path)

if __name__ == '__main__':
    main()
