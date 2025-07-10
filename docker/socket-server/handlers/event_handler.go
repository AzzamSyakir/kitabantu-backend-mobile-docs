package handlers

import (
	"encoding/json"
	"fmt"
	"log"
	"socket-server/models"

	"github.com/gorilla/websocket"
)

type Client struct {
	Conn   *websocket.Conn
	UserId string
	Room   string
}

var clients = make(map[*websocket.Conn]*Client)
var rooms = make(map[string]map[*websocket.Conn]bool)

func HandleJoinRoomEvent(conn *websocket.Conn, payload models.ChatPayload) {
	userId := payload.UserId
	room := payload.Room

	clients[conn] = &Client{
		Conn:   conn,
		UserId: userId,
		Room:   room,
	}

	if rooms[room] == nil {
		rooms[room] = make(map[*websocket.Conn]bool)
	}
	rooms[room][conn] = true

	Broadcast(nil, map[string]string{
		"event":   "joined_room",
		"type":    "joined",
		"user_id": userId,
		"room":    room,
		"chat":    userId + " joined the room",
	})
}

func HandleSendChatEvent(conn *websocket.Conn, payload models.ChatPayload) {
	userId := payload.UserId
	chat := payload.Chat
	room := payload.Room
	file := payload.File
	if userId == "" || room == "" {
		log.Println("send_chat missing fields")
		return
	}

	data := map[string]string{
		"event":   "chat_room",
		"user_id": userId,
		"chat":    chat,
		"room":    room,
		"file":    file,
	}
	fmt.Println("data ", data)
	Broadcast(conn, data)
}

func Broadcast(sender *websocket.Conn, data map[string]string) {
	room := data["room"]
	event := data["event"]

	if room == "" || event == "" {
		log.Println("Missing room or event in payload")
		return
	}

	jsonData, err := json.Marshal(data)
	if err != nil {
		log.Println("Error marshaling JSON:", err)
		return
	}
	for conn := range rooms[room] {
		err := conn.WriteMessage(websocket.TextMessage, jsonData)
		if err != nil {
			log.Println("Write error:", err)
			conn.Close()
			delete(rooms[room], conn)
			delete(clients, conn)
		}
	}
}
