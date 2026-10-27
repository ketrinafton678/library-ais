import java.sql.*;
import java.util.Scanner;

public class Main {
    // Параметры подключения к вашему PostgreSQL в Open Server
    private static final String URL = "jdbc:postgresql://localhost:5432/library_db";
    private static final String USER = "postgres";
    private static final String PASSWORD = "postgres"; // Ваш пароль (или оставьте пустым, если trust)

    public static void main(String[] args) {
        // 1. Установление связи с БД (Пункт 2.2 задания)
        try (Connection conn = DriverManager.getConnection(URL, USER, PASSWORD)) {
            System.out.println("Успешное подключение к базе данных PostgreSQL!");

            // Бесконечный цикл для меню
            Scanner scanner = new Scanner(System.in);
            while (true) {
                System.out.println("\n--- МЕНЮ БИБЛИОТЕКИ ---");
                System.out.println("1. Вывести список всех книг");
                System.out.println("2. Добавить новую книгу");
                System.out.println("3. Выдать книгу абоненту");
                System.out.println("0. Выход");
                System.out.print("Выберите действие: ");

                String choice = scanner.nextLine();

                switch (choice) {
                    case "1":
                        printAllBooks(conn); // Пункт 2.3 (Вывод информации)
                        break;
                    case "2":
                        addNewBook(conn, scanner); // Пункт 2.4 (Редактирование/добавление)
                        break;
                    case "3":
                        issueBook(conn, scanner); // Пункт 2.5 (Выдача книг)
                        break;
                    case "0":
                        System.out.println("Выход из программы.");
                        return;
                    default:
                        System.out.println("Неверный ввод. Попробуйте снова.");
                }
            }
        } catch (SQLException e) {
            System.out.println("Ошибка подключения или выполнения запроса:");
            e.printStackTrace();
        }
    }

    // --- МЕТОДЫ ДЛЯ РАБОТЫ С БАЗОЙ ---

    // Вывод перечня книг с их расположением 
    private static void printAllBooks(Connection conn) {
        // SQL-запрос с JOIN, чтобы вытащить имя автора и жанра, а не просто их ID
        String sql = "SELECT b.id, b.title, a.full_name AS author, g.name AS genre, " +
                "b.inventory_number, b.status " +
                "FROM books b " +
                "LEFT JOIN authors a ON b.author_id = a.id " +
                "LEFT JOIN genres g ON b.genre_id = g.id " +
                "ORDER BY b.id";

        try (Statement stmt = conn.createStatement();
                ResultSet rs = stmt.executeQuery(sql)) {

            System.out.printf("%-5s | %-30s | %-20s | %-15s | %-10s | %-10s%n",
                    "ID", "Название", "Автор", "Жанр", "Инв. номер", "Статус");
            System.out.println("-".repeat(100));

            while (rs.next()) {
                System.out.printf("%-5d | %-30s | %-20s | %-15s | %-10s | %-10s%n",
                        rs.getInt("id"),
                        rs.getString("title"),
                        rs.getString("author") != null ? rs.getString("author") : "Неизвестен",
                        rs.getString("genre") != null ? rs.getString("genre") : "Без жанра",
                        rs.getString("inventory_number"),
                        rs.getString("status"));
            }
        } catch (SQLException e) {
            System.out.println("Ошибка при выводе книг: " + e.getMessage());
        }
    }

    // Добавление новой книги (Пункт 2.4)
    private static void addNewBook(Connection conn, Scanner scanner) {
        System.out.print("Введите название книги: ");
        String title = scanner.nextLine();
        System.out.print("Введите ID автора (например, 1): ");
        int authorId = Integer.parseInt(scanner.nextLine());
        System.out.print("Введите инвентарный номер (уникальный): ");
        String invNumber = scanner.nextLine();

        String sql = "INSERT INTO books (title, author_id, inventory_number, status) VALUES (?, ?, ?, 'available')";

        try (PreparedStatement pstmt = conn.prepareStatement(sql)) {
            pstmt.setString(1, title);
            pstmt.setInt(2, authorId);
            pstmt.setString(3, invNumber);

            int rows = pstmt.executeUpdate();
            if (rows > 0) {
                System.out.println(" Книга успешно добавлена!");
            }
        } catch (SQLException e) {
            System.out.println("Ошибка при добавлении: " + e.getMessage());
        }
    }

    // Выдача книги (Пункт 2.5)
    private static void issueBook(Connection conn, Scanner scanner) {
        System.out.print("Введите ID книги для выдачи: ");
        int bookId = Integer.parseInt(scanner.nextLine());
        System.out.print("Введите ID абонента: ");
        int subId = Integer.parseInt(scanner.nextLine());

        // 1. Меняем статус книги на 'issued'
        String updateSql = "UPDATE books SET status = 'issued' WHERE id = ? AND status = 'available'";

        // 2. Записываем в журнал выдачи
        String insertSql = "INSERT INTO book_loans (book_id, subscriber_id) VALUES (?, ?)";

        try {
            conn.setAutoCommit(false); // Начинаем транзакцию

            try (PreparedStatement pstmtUpdate = conn.prepareStatement(updateSql)) {
                pstmtUpdate.setInt(1, bookId);
                int updated = pstmtUpdate.executeUpdate();

                if (updated == 0) {
                    System.out.println("Ошибка: Книга не найдена или уже выдана!");
                    conn.rollback();
                    return;
                }
            }

            try (PreparedStatement pstmtInsert = conn.prepareStatement(insertSql)) {
                pstmtInsert.setInt(1, bookId);
                pstmtInsert.setInt(2, subId);
                pstmtInsert.executeUpdate();
            }

            conn.commit(); // Фиксируем изменения
            System.out.println("Книга успешно выдана абоненту!");

        } catch (SQLException e) {
            try {
                conn.rollback();
            } catch (SQLException ex) {
                ex.printStackTrace();
            }
            System.out.println("Ошибка при выдаче: " + e.getMessage());
        } finally {
            try {
                conn.setAutoCommit(true);
            } catch (SQLException e) {
                e.printStackTrace();
            }
        }
    }
}