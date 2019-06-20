#include <cassert>
#include <deque>
#include <optional>
#include <random>
#include <utility>
#include <vector>

using std::deque;
using std::function;
using std::move;
using std::optional;
using std::pair;
using std::vector;

const int NUM_PLAYERS = 7;
const int MAX_PICKED_NUMBER = 100;
const int MIN_INTERVAL_BETWEEN_JOINS = 5000;
const int MAX_INTERVAL_BETWEEN_JOINS = 60000;
const int GAME_SERVER_REQUEST_PROCESSING_TIME = 10;
const int GAME_SERVER_EVALUATION_TIME = 30;
const int REQUEST_DT_REPORT_SAMPLE = 100;
const int MAX_SERVERS = 12;

std::default_random_engine g_random_engine;
int g_next_game_id = 1;
const bool g_log = false;
int g_cycle = 0;

struct Graph
{
    vector<pair<int, int>> delays;
    vector<pair<int, int>> servers;
    vector<pair<int, int>> players;
};

class GameServer
{
    struct Request
    {
        int age = GAME_SERVER_REQUEST_PROCESSING_TIME;
        int player_id;
        int picked_number;
        std::function<void(int)> response_subject;
        Request(int player_id, int picked_number, std::function<void(int)> response_subject)
            : player_id(player_id),
              picked_number(picked_number),
              response_subject(move(response_subject))
        {
        }
    };
    deque<Request> requests;
    vector<Request> current_game;
    int current_game_age = GAME_SERVER_EVALUATION_TIME;

    void advance_current_game()
    {
        if (--current_game_age > 0) {
            return;
        }
        int game_id = g_next_game_id++;
        for (auto& r : current_game) {
            r.response_subject(game_id);
            if (g_log)
                fprintf(stderr, "[%d] Process join %d %d\n", g_cycle, r.player_id, r.picked_number);
        }
        current_game.clear();
        current_game_age = GAME_SERVER_EVALUATION_TIME;
    }

public:
    void join(int player_id, int picked_number, std::function<void(int)> response_subject)
    {
        requests.emplace_back(player_id, picked_number, move(response_subject));
    }
    void advance()
    {
        if (current_game.size() == NUM_PLAYERS) {
            advance_current_game();
            return;
        }

        if (requests.empty()) {
            return;
        }

        auto& r = requests.front();
        if (--r.age > 0) {
            return;
        }
        if (g_log)
            fprintf(stderr, "[%d] Accept join %d %d\n", g_cycle, r.player_id, r.picked_number);
        current_game.emplace_back(move(r));
        requests.pop_front();
    }
    int queue_size() const { return current_game.size() + requests.size(); }
};

class Internet
{
    vector<GameServer>& game_servers;
    deque<int> request_dts;
    int servers_online = 0;

public:
    Internet(vector<GameServer>& game_servers) : game_servers(game_servers) {}
    void join(int player_id, int picked_number, std::function<void(int)> response_subject)
    {
        assert(!game_servers.empty());
        vector<int> not_done, done;
        for (int i = 0; i < game_servers.size(); ++i) {
            auto& s = game_servers[i];
            if (s.queue_size() % NUM_PLAYERS == 0) {
                done.push_back(i);
            } else {
                not_done.push_back(i);
            }
        }
        for (int i = 0; i < done.size() && not_done.size() < servers_online; ++i) {
            not_done.push_back(done[i]);
        }
        std::uniform_int_distribution<> uid(0, not_done.size() - 1);
        int idx = not_done[uid(g_random_engine)];
        game_servers[idx].join(player_id, picked_number, move(response_subject));
    }
    void report_request_dt(int dt)
    {
        request_dts.push_back(dt);
        if (request_dts.size() > REQUEST_DT_REPORT_SAMPLE) {
            request_dts.pop_front();
        }
    }
    int request_dt()
    {
        if (request_dts.empty()) {
            return 0;
        }
        return *max_element(request_dts.begin(), request_dts.end());
    }
    void set_servers_online(int so) { servers_online = so; }
};

class PlayerFrontEnd
{
    Internet& internet;
    const int player_id;

    int time_to_next_join = 0;
    void (PlayerFrontEnd::*next_function)() = nullptr;
    int next_picked_number = -1;
    int request_sent_time;

    void idle()
    {
        if (--time_to_next_join > 0) {
            return;
        }
        join();
    }
    void expect_join_response() {}
    void join()
    {
        if (next_picked_number <= 0) {
            std::uniform_int_distribution<> uid(1, MAX_PICKED_NUMBER);
            next_picked_number = uid(g_random_engine);
        }
        if (g_log)
            fprintf(stderr, "[%d] playerjoin %d %d\n", g_cycle, player_id, next_picked_number);
        internet.join(player_id, next_picked_number,
                      [this](int received_game_id) { receive_join_response(received_game_id); });
        request_sent_time = g_cycle;
        next_function = &PlayerFrontEnd::expect_join_response;
    }
    void receive_join_response(int received_game_id)
    {
        assert(next_function == &PlayerFrontEnd::expect_join_response);
        next_function = &PlayerFrontEnd::idle;
        set_time_to_next_join();
        int dt = g_cycle - request_sent_time;
        internet.report_request_dt(dt);
    }
    void set_time_to_next_join()
    {
        std::uniform_int_distribution<> uid(MIN_INTERVAL_BETWEEN_JOINS, MAX_INTERVAL_BETWEEN_JOINS);
        time_to_next_join = uid(g_random_engine);
    }

public:
    PlayerFrontEnd(Internet& internet, int player_id) : internet(internet), player_id(player_id)
    {
        next_function = &PlayerFrontEnd::idle;
        set_time_to_next_join();
    }
    void advance() { (this->*next_function)(); }
};

void print_to_m(FILE* f, const char* name, const vector<pair<int, int>>& v)
{
    fprintf(f, "%s = [\n", name);
    for (auto& p : v) {
        fprintf(f, "%d, %d\n", p.first, p.second);
    }
    fprintf(f, "];\n");
}

int main()
{
    Graph gr;
    vector<PlayerFrontEnd> players;
    vector<GameServer> all_servers;
    for (int i = 0; i < MAX_SERVERS; ++i) {
        all_servers.emplace_back();
    }
    Internet internet(all_servers);
    for (int i = 0; i < 10000; ++i) {
        players.emplace_back(internet, i);
    }
    int servers_online = 2;
    internet.set_servers_online(servers_online);
    int prev_rdt = 0;
    int increase_servers_since = 0;
    int decrease_servers_since = 0;
    int players_online;
    for (; g_cycle < 450000; ++g_cycle) {
        players_online = 150000 <= g_cycle && g_cycle <= 300000 ? 10000 : 2000;
        if (gr.players.empty()) {
            gr.players.emplace_back(g_cycle, players_online);
        } else if (gr.players.back().second != players_online) {
            gr.players.emplace_back(g_cycle - 1, gr.players.back().second);
            gr.players.emplace_back(g_cycle, players_online);
        }
        for (int i = 0; i < players_online; ++i) {
            players[i].advance();
        }
        for (auto& s : all_servers) {
            s.advance();
        }
        int rdt = internet.request_dt();
        if (rdt != prev_rdt) {
            prev_rdt = rdt;
            //            fprintf(stderr, "[%d] %d\n", g_cycle, rdt);
            gr.delays.emplace_back(g_cycle, rdt);
        }
        if (false) {
            int total = 0;
            for (int i = 0; i < servers_online; ++i) {
                total += all_servers[i].queue_size();
            }
            int underload = servers_online * NUM_PLAYERS;
            int overload = 2 * underload;
            if (total > overload && servers_online < MAX_SERVERS) {
                decrease_servers_since = 0;
                if (increase_servers_since == 0) {
                    increase_servers_since = g_cycle;
                } else if (g_cycle - increase_servers_since > 1000) {
                    ++servers_online;
                    internet.set_servers_online(servers_online);
                    printf("Servers: %d\n", servers_online);
                    increase_servers_since = 0;
                }
            } else if (total < underload && servers_online > 1) {
                increase_servers_since = 0;
                if (decrease_servers_since == 0) {
                    decrease_servers_since = g_cycle;
                } else if (g_cycle - decrease_servers_since > 1000) {
                    servers_online--;
                    internet.set_servers_online(servers_online);
                    printf("Servers: %d\n", servers_online);
                    decrease_servers_since = 0;
                }
            }
        }
        if (gr.servers.empty()) {
            gr.servers.emplace_back(g_cycle, servers_online);
        } else if (gr.servers.back().second != servers_online) {
            gr.servers.emplace_back(g_cycle, servers_online);
        }
    }
    gr.players.emplace_back(g_cycle, players_online);
    FILE* f = fopen("/tmp/a.m", "wt");
    print_to_m(f, "d", gr.delays);
    print_to_m(f, "s", gr.servers);
    print_to_m(f, "p", gr.players);
    fclose(f);
    return EXIT_SUCCESS;
}
