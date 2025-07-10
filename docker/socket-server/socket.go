package main

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os"
	"socket-server/handlers"
	"socket-server/models"
	"strconv"
	"time"

	"github.com/go-redis/redis"
	"github.com/gorilla/websocket"
	"github.com/joho/godotenv"
)

var (
	ctx         = context.Background()
	connections = make(map[string]*websocket.Conn)
	upgrader    = websocket.Upgrader{}
	eventRouter = make(map[string]EventHandler)
)

type EventHandler func(conn *websocket.Conn, payload models.ChatPayload)

type Env struct {
	RedisHost        string
	RedisPort        string
	RedisDB          string
	SocketChannel    string
	SocketPort       string
	SocketCorsOrigin string
}

func main() {
	_ = godotenv.Load("../../.env")
	env := GenerateEnv()
	RegisterEventHandlers()
	conn := SetupWebSocket()
	port := ":8080"
	log.Println("WebSocket server running on port", port)
	go SubscribeToRedis(env, conn)
	log.Fatal(http.ListenAndServe(port, nil))
	log.Println("Listening on port", env.SocketPort)
	log.Fatal(http.ListenAndServe(":"+env.SocketPort, nil))
}
func RegisterEventHandlers() {
	eventRouter["join_room"] = handlers.HandleJoinRoomEvent
	eventRouter["send_chat"] = handlers.HandleSendChatEvent
}
func GenerateEnv() Env {
	return Env{
		RedisHost:        os.Getenv("REDIS_HOST"),
		RedisPort:        os.Getenv("REDIS_PORT"),
		RedisDB:          os.Getenv("SOCKET_REDIS_DB"),
		SocketChannel:    os.Getenv("SOCKET_CHANNEL"),
		SocketPort:       os.Getenv("SOCKET_PORT"),
		SocketCorsOrigin: os.Getenv("SOCKET_CORS_ORIGIN"),
	}
}
func SetupWebSocket() *websocket.Conn {
	var websocketConn *websocket.Conn
	http.HandleFunc("/ws", func(w http.ResponseWriter, r *http.Request) {
		upgrader.CheckOrigin = func(r *http.Request) bool { return true }

		conn, err := upgrader.Upgrade(w, r, nil)
		if err != nil {
			log.Println("WebSocket upgrade error:", err)
			return
		}
		go HandleWebSocketConnection(conn)
		websocketConn = conn
	})
	return websocketConn
}
func HandleWebSocketConnection(conn *websocket.Conn) {
	defer conn.Close()

	for {
		_, msg, err := conn.ReadMessage()
		if err != nil {
			log.Println("Read error:", err)
			break
		}

		var payload models.ChatPayload
		if err := json.Unmarshal(msg, &payload); err != nil {
			log.Println("Invalid JSON:", err)
			continue
		}

		event := payload.Event
		if handler, ok := eventRouter[event]; ok {
			handler(conn, payload)
		} else {
			log.Println("Unknown event:", event)
		}
	}
}
func NewRedisClient(env Env) *redis.Client {
	db, _ := strconv.Atoi(env.RedisDB)
	return redis.NewClient(&redis.Options{
		Addr: fmt.Sprintf("%s:%s", env.RedisHost, env.RedisPort),
		DB:   db,
	})
}
func SubscribeToRedis(env Env, conn *websocket.Conn) {
	client := NewRedisClient(env)
	pubsub := client.Subscribe(env.SocketChannel)

	for {
		msg, err := pubsub.ReceiveMessage()
		if err != nil {
			log.Println("Redis receive error:", err)
			time.Sleep(time.Second)
			continue
		}

		var payload models.ChatPayload
		if err := json.Unmarshal([]byte(msg.Payload), &payload); err != nil {
			log.Println("Invalid payload:", err)
			continue
		}

		if payload.UserId == "" || payload.Event == "" || payload.Room == "" {
			log.Println("Missing fields, skip broadcast")
			continue
		}

		event := payload.Event
		if handler, ok := eventRouter[event]; ok {
			handler(conn, payload)
		} else {
			log.Println("Unknown event:", event)
		}
	}
}
